<?php

/**
 * Browsing and designing: the shop's landing pages, product pages, the
 * premade designs and the design studio.
 */
class ShopController extends Controller {
    public function index(): void {
        // Shop landing page - choose between premade and custom
        $this->render('shop/index');
    }

    public function premade(): void {
        // Show category sections (Anime, Coming Soon, etc.)
        $this->render('shop/premade');
    }

    public function anime(): void {

        // Each card previews the design on its first associated product. That
        // link row is resolved once (dpf) so the image, price and PLACEMENT all
        // describe the same garment — placement is per product, so a position
        // taken from a different one would put the print in the wrong spot.
        $stmt = $this->db->prepare("
            SELECT d.*,
                   pf.image_path      AS product_image_path,
                   pf.back_image_path AS product_back_image_path,
                   pf.base_price      AS product_base_price,
                   pf.name            AS product_name,
                   dpf.design_pos_x         AS link_pos_x,
                   dpf.design_pos_y         AS link_pos_y,
                   dpf.design_pos_size      AS link_pos_size,
                   dpf.design_pos_back_x    AS link_pos_back_x,
                   dpf.design_pos_back_y    AS link_pos_back_y,
                   dpf.design_pos_back_size AS link_pos_back_size
            FROM premade_designs d
            JOIN design_sections s ON d.section_id = s.id
            LEFT JOIN design_products dpf ON dpf.id = (
                SELECT dp.id FROM design_products dp
                JOIN products p ON p.id = dp.product_id
                WHERE dp.design_id = d.id AND p.active = 1
                ORDER BY dp.id ASC LIMIT 1
            )
            LEFT JOIN products pf ON pf.id = dpf.product_id
            WHERE s.slug = 'anime' AND d.active = 1
            ORDER BY d.name
        ");
        $stmt->execute();
        $designs = $stmt->fetchAll();

        // Where that product has its own placement, it wins over the design's.
        foreach ($designs as &$d) {
            foreach (['x', 'y', 'size', 'back_x', 'back_y', 'back_size'] as $k) {
                if (isset($d["link_pos_$k"])) {
                    $d["design_pos_$k"] = $d["link_pos_$k"];
                }
            }
        }
        unset($d);

        $favoriteDesignIds = (new Favorites($this->db))->idsForCurrentUser('design');

        $this->render('shop/anime', ['designs' => $designs, 'favoriteDesignIds' => $favoriteDesignIds]);
    }

    public function premadeDesign(): void {
        // Get design ID from URL
        $uri = $_SERVER['REQUEST_URI'];
        $parts = explode('/', trim(parse_url($uri, PHP_URL_PATH), '/'));
        $designId = (int) end($parts);

        // Get design details
        $stmt = $this->db->prepare("
            SELECT d.*, s.name as section_name, s.slug as section_slug, s.icon as section_icon
            FROM premade_designs d
            JOIN design_sections s ON d.section_id = s.id
            WHERE d.id = ? AND d.active = 1
        ");
        $stmt->execute([$designId]);
        $design = $stmt->fetch();

        if (!$design) {
            http_response_code(404);
            echo "Design not found";
            return;
        }

        // Get products this design is available on (with their sizes and colors).
        // Skip products that don't have a mockup image yet — the design page
        // can't render a preview without one, and selecting them would leave
        // the previous product's image on screen.
        // The placement columns come off the LINK row: the same design sits in a
        // different spot on a tee than on a hoodie. Fall back to the design's own
        // position for links the admin hasn't positioned yet.
        $stmt = $this->db->prepare("
            SELECT p.*,
                   (SELECT COUNT(*) FROM product_sizes WHERE product_id = p.id AND is_available = 1) as size_count,
                   COALESCE(dp.design_pos_x,         d.design_pos_x,         0)  AS design_pos_x,
                   COALESCE(dp.design_pos_y,         d.design_pos_y,         0)  AS design_pos_y,
                   COALESCE(dp.design_pos_size,      d.design_pos_size,      55) AS design_pos_size,
                   COALESCE(dp.design_pos_back_x,    d.design_pos_back_x,    0)  AS design_pos_back_x,
                   COALESCE(dp.design_pos_back_y,    d.design_pos_back_y,    0)  AS design_pos_back_y,
                   COALESCE(dp.design_pos_back_size, d.design_pos_back_size, 55) AS design_pos_back_size
            FROM products p
            JOIN design_products dp ON p.id = dp.product_id
            JOIN premade_designs d  ON d.id = dp.design_id
            WHERE dp.design_id = ?
              AND p.active = 1
              AND p.image_path IS NOT NULL
              AND p.image_path <> ''
            ORDER BY p.name
        ");
        $stmt->execute([$designId]);
        $availableProducts = $stmt->fetchAll();

        // Get sizes for each product
        foreach ($availableProducts as &$product) {
            $stmt = $this->db->prepare("
                SELECT ps.*, 
                       GROUP_CONCAT(ac.color_name ORDER BY ac.id) as color_names,
                       GROUP_CONCAT(ac.color_hex ORDER BY ac.id) as color_hexes,
                       GROUP_CONCAT(ac.id ORDER BY ac.id) as color_ids
                FROM product_sizes ps
                JOIN product_variants pv ON ps.id = pv.size_id AND pv.is_available = 1
                LEFT JOIN available_colors ac ON pv.color_id = ac.id
                WHERE ps.product_id = ? AND ps.is_available = 1
                GROUP BY ps.id
                ORDER BY ps.size_order
            ");
            $stmt->execute([$product['id']]);
            $product['sizes'] = $stmt->fetchAll();
        }
        unset($product);

        // base_price is the SUPPLIER cost — attach the qty-1 retail price for
        // the initial render. The page's JS recomputes with Pricing.unitPrice
        // (lib/pricing.js) as quantity/product change; this keeps the first paint
        // consistent with those later updates.
        foreach ($availableProducts as &$product) {
            $product['retail_price'] = Pricing::unitPrice(
                (float)$product['base_price'],
                Pricing::categoryFor($product['slug'] ?? '', $product['name'] ?? ''),
                1
            );
        }
        unset($product);

        $this->render('shop/premade_design', ['design' => $design, 'availableProducts' => $availableProducts]);
    }

public function designer(): void {
        // Check if loading an existing design (requires login)
        $loadDesign = null;
        if (!empty($_GET['load']) && Auth::check()) {
            $designId = (int)$_GET['load'];
            $userId = Auth::userId();
            
            // Fetch the design (only if it belongs to the current user)
            // Carries the design's OWN product data — base price, slug and the
            // design-area boxes. The editor previously looked the product up in
            // productsData, which only lists active products that have artwork,
            // so opening a design whose product was later deactivated silently
            // failed and nothing rendered. Everything the editor needs now
            // travels with the design itself.
            $stmt = $this->db->prepare("
                SELECT cd.*, p.name as product_name, p.slug as product_slug,
                       p.base_price, p.size_chart_image,
                       p.image_path, p.back_image_path,
                       p.left_sleeve_image_path, p.right_sleeve_image_path,
                       p.active AS product_active,
                       p.da_front_x, p.da_front_y, p.da_front_w, p.da_front_h,
                       p.da_back_x,  p.da_back_y,  p.da_back_w,  p.da_back_h,
                       p.da_lsleeve_x, p.da_lsleeve_y, p.da_lsleeve_w, p.da_lsleeve_h,
                       p.da_rsleeve_x, p.da_rsleeve_y, p.da_rsleeve_w, p.da_rsleeve_h,
                       ac.color_hex as saved_color_hex, ac.color_name
                FROM custom_designs cd
                LEFT JOIN products p ON cd.product_id = p.id
                LEFT JOIN available_colors ac ON cd.color_id = ac.id
                WHERE cd.id = ? AND cd.user_id = ?
            ");
            $stmt->execute([$designId, $userId]);
            $loadDesign = $stmt->fetch();
            
            // Also fetch the uploads and texts separately for proper image paths
            if ($loadDesign) {
                try {
                    $stmt = $this->db->prepare("SELECT * FROM custom_design_uploads WHERE design_id = ? ORDER BY layer_order");
                    $stmt->execute([$designId]);
                    $loadDesign['uploads'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    $loadDesign['uploads'] = [];
                }
                
                try {
                    $stmt = $this->db->prepare("SELECT * FROM custom_design_texts WHERE design_id = ? ORDER BY layer_order");
                    $stmt->execute([$designId]);
                    $loadDesign['texts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    $loadDesign['texts'] = [];
                }
            }
        }
        
        // Get all active products with their sizes and colors. Same image-path
        // filter as viewDesign — products without a mockup PNG can't be
        // rendered in the studio, so they're hidden until artwork is uploaded.
        $stmt = $this->db->query("
            SELECT p.*,
                   (SELECT COUNT(*) FROM product_sizes WHERE product_id = p.id AND is_available = 1) as size_count
            FROM products p
            WHERE p.active = 1
              AND p.image_path IS NOT NULL
              AND p.image_path <> ''
            ORDER BY p.name
        ");
        $products = $stmt->fetchAll();

        // Get sizes and colors for each product
        foreach ($products as &$product) {
            $stmt = $this->db->prepare("
                SELECT ps.*, 
                       GROUP_CONCAT(ac.color_name ORDER BY ac.id) as color_names,
                       GROUP_CONCAT(ac.color_hex ORDER BY ac.id) as color_hexes,
                       GROUP_CONCAT(ac.id ORDER BY ac.id) as color_ids
                FROM product_sizes ps
                JOIN product_variants pv ON ps.id = pv.size_id AND pv.is_available = 1
                LEFT JOIN available_colors ac ON pv.color_id = ac.id
                WHERE ps.product_id = ? AND ps.is_available = 1
                GROUP BY ps.id
                ORDER BY ps.size_order
            ");
            $stmt->execute([$product['id']]);
            $product['sizes'] = $stmt->fetchAll();
        }
        unset($product);

        $this->render('shop/designer', ['products' => $products, 'loadDesign' => $loadDesign]);
    }

    public function selectProduct(): void {
        // Hide products without a mockup image — they can't be previewed in
        // the customizer flow that this picker leads into.
        $stmt = $this->db->query("
            SELECT p.*, (SELECT COUNT(*) FROM product_sizes WHERE product_id = p.id AND is_available = 1) as size_count
              FROM products p
             WHERE p.active = 1
               AND p.image_path IS NOT NULL
               AND p.image_path <> ''
             ORDER BY p.name");
        $products = $stmt->fetchAll();
        // Get sizes and colors for each product
        foreach ($products as &$product) {
            $stmt = $this->db->prepare("SELECT ps.*, GROUP_CONCAT(ac.color_name ORDER BY ac.id) as color_names, GROUP_CONCAT(ac.color_hex ORDER BY ac.id) as color_hexes, GROUP_CONCAT(ac.id ORDER BY ac.id) as color_ids FROM product_sizes ps JOIN product_variants pv ON ps.id = pv.size_id AND pv.is_available = 1 LEFT JOIN available_colors ac ON pv.color_id = ac.id WHERE ps.product_id = ? AND ps.is_available = 1 GROUP BY ps.id ORDER BY ps.size_order");
            $stmt->execute([$product['id']]);
            $product['sizes'] = $stmt->fetchAll();
        }
        unset($product);
        $favoriteProductIds = (new Favorites($this->db))->idsForCurrentUser('product');

        $this->render('shop/select_product', ['products' => $products, 'favoriteProductIds' => $favoriteProductIds]);
    }

    // Set selected product ID in session
    public function setSelectedProduct(): void {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['product_id'] ?? null;
        if ($id) {
            $_SESSION['selected_product_id'] = $id;
            echo json_encode(['success' => true]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No product ID']);
        }
    }

    // Removed duplicate declaration
    public function customProduct(): void {
        $id = $_SESSION['selected_product_id'] ?? null;
        if (!$id) {
            // Direct hit without picking a product first — send them to the
            // picker rather than a bare 400.
            header('Location: /shop/select_product');
            return;
        }
        // Get product details
        $stmt = $this->db->prepare("SELECT * FROM products WHERE id = ? AND active = 1");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        if (!$product) {
            http_response_code(404);
            echo "Product not found";
            return;
        }
        // Get all available colors for this product
        $stmt = $this->db->prepare("
            SELECT ac.id, ac.color_name AS name, ac.color_hex AS hex
            FROM available_colors ac
            JOIN product_variants pv ON pv.color_id = ac.id AND pv.is_available = 1
            WHERE pv.product_id = ?
            GROUP BY ac.id
            ORDER BY ac.id
        ");
        $stmt->execute([$id]);
        $colors = $stmt->fetchAll();
        // Get all available sizes for this product — a size counts only while
        // it is offered and comes in at least one colour.
        $stmt = $this->db->prepare("
            SELECT ps.id, ps.size_name
            FROM product_sizes ps
            WHERE ps.product_id = ?
              AND ps.is_available = 1
              AND EXISTS (SELECT 1 FROM product_variants pv WHERE pv.size_id = ps.id AND pv.is_available = 1)
            ORDER BY ps.size_order
        ");
        $stmt->execute([$id]);
        $sizes = $stmt->fetchAll();
        // Build color-to-size matrix
        $stmt = $this->db->prepare("
            SELECT pv.color_id, pv.size_id
            FROM product_variants pv
            WHERE pv.product_id = ?
              AND pv.is_available = 1
        ");
        $stmt->execute([$id]);
        $matrixRows = $stmt->fetchAll();
        $colorSizeMatrix = [];
        foreach ($matrixRows as $row) {
            $colorSizeMatrix[$row['color_id']][] = $row['size_id'];
        }
        // Get product thumbnails (if you have a table or logic for this)
        $thumbnails = [];
        // Example: if you have a product_images table:
        // $stmt = $this->db->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order");
        // $stmt->execute([$id]);
        // $thumbnails = array_column($stmt->fetchAll(), 'image_path');

        // Delivery estimate. Was a hard-coded "Mon, Feb 2" in the view, shown to
        // every customer forever. The shipping page states items print in 3–5
        // business days, so quote the far end of that window, skipping weekends.
        $deliveryEstimate = $this->businessDaysFromNow(5);

        // Single-unit retail price, for the sticky action bar on phones.
        // products.base_price is the SUPPLIER cost, so it has to go through
        // the pricing engine — rendering it raw would advertise a EUR 2.14
        // t-shirt. lib/price-tiers.js takes over as soon as it has run; this is
        // what the bar shows on the first paint, so that it never flashes a
        // zero before the script catches up.
        $retailPrice = Pricing::unitPrice(
            (float)($product['base_price'] ?? 0),
            Pricing::categoryFor($product['slug'] ?? '', $product['name'] ?? ''),
            1
        );

        $this->render('shop/custom_product', ['product' => $product, 'colors' => $colors, 'sizes' => $sizes, 'colorSizeMatrix' => $colorSizeMatrix, 'thumbnails' => $thumbnails, 'deliveryEstimate' => $deliveryEstimate, 'retailPrice' => $retailPrice]);
    }

    public function product(?int $id = null): void {
        if (!$id) {
            header('Location: /shop');
            exit;
        }

        // Get product details
        $stmt = $this->db->prepare("SELECT * FROM products WHERE id = ? AND active = 1");
        $stmt->execute([$id]);
        $product = $stmt->fetch();

        if (!$product) {
            http_response_code(404);
            echo "Product not found";
            return;
        }

        // Get product variants. product_variants only stores colour/size as FKs,
        // so resolve them here - the view renders swatches and a size dropdown and
        // needs the hex and the size name, not the ids.
        $stmt = $this->db->prepare("
            SELECT pv.id, pv.product_id, pv.stock_quantity, pv.is_available,
                   ac.color_hex     AS color,
                   ac.color_name    AS color_name,
                   ps.size_name     AS size,
                   ps.size_order,
                   COALESCE(ps.price_modifier, 0) AS price_modifier,
                   COALESCE(pv.unit_price, p.base_price + COALESCE(ps.price_modifier, 0)) AS supplier_cost
            FROM product_variants pv
            JOIN products p               ON p.id = pv.product_id
            LEFT JOIN available_colors ac ON ac.id = pv.color_id
            LEFT JOIN product_sizes   ps ON ps.id = pv.size_id
            WHERE pv.product_id = ?
              AND pv.is_available = 1
              AND ps.is_available = 1
              AND ac.color_hex IS NOT NULL
              AND ps.size_name IS NOT NULL
            ORDER BY ps.size_order, ac.id
        ");
        $stmt->execute([$id]);
        $variants = $stmt->fetchAll();

        // Attach customer-facing prices. supplier_cost mirrors the exact
        // expression resolveSupplierCost() uses at add-to-cart, so the price
        // shown per size equals the price charged for qty 1.
        $category = Pricing::categoryFor($product['slug'] ?? '', $product['name'] ?? '');
        $product['retail_price'] = Pricing::unitPrice((float)$product['base_price'], $category, 1);
        foreach ($variants as &$v) {
            $v['retail_price'] = Pricing::unitPrice((float)$v['supplier_cost'], $category, 1);
        }
        unset($v);

        $this->render('shop/product', ['product' => $product, 'variants' => $variants]);
    }

    /**
     * API endpoint to get product variants with sizes and colors for cart modal
     */
    public function productVariants(): void {
        header('Content-Type: application/json');
        
        // Get product ID from URL
        $uri = $_SERVER['REQUEST_URI'];
        preg_match('/\/api\/product-variants\/(\d+)/', $uri, $matches);
        $productId = $matches[1] ?? null;
        
        if (!$productId) {
            http_response_code(400);
            echo json_encode(['error' => 'Product ID required']);
            return;
        }

        // Get the sizes this product is offered in (and that come in a colour)
        $stmt = $this->db->prepare("
            SELECT ps.id, ps.size_name as name, ps.size_order
            FROM product_sizes ps
            WHERE ps.product_id = ?
              AND ps.is_available = 1
              AND EXISTS (SELECT 1 FROM product_variants pv WHERE pv.size_id = ps.id AND pv.is_available = 1)
            ORDER BY ps.size_order
        ");
        $stmt->execute([$productId]);
        $sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get all colors available for this product
        $stmt = $this->db->prepare("
            SELECT DISTINCT ac.id, ac.color_name as name, ac.color_hex as hex
            FROM available_colors ac
            INNER JOIN product_variants pv ON ac.id = pv.color_id AND pv.is_available = 1
            WHERE pv.product_id = ?
            ORDER BY ac.color_name
        ");
        $stmt->execute([$productId]);
        $colors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get all variants with availability
        $stmt = $this->db->prepare("
            SELECT pv.id, pv.size_id, pv.color_id, pv.stock_quantity, pv.is_available
            FROM product_variants pv
            WHERE pv.product_id = ?
        ");
        $stmt->execute([$productId]);
        $variants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'sizes' => $sizes,
            'colors' => $colors,
            'variants' => $variants
        ]);
    }

    /**
     * Date $days business days from today, formatted for display.
     * Used for delivery estimates so the page never shows a frozen literal date.
     */
    private function businessDaysFromNow(int $days): string {
        $d = new DateTimeImmutable('today');
        while ($days > 0) {
            $d = $d->modify('+1 day');
            if ((int)$d->format('N') < 6) {   // 6 = Sat, 7 = Sun
                $days--;
            }
        }
        return $d->format('D, j M');
    }
}
