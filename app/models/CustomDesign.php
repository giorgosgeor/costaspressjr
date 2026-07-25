<?php
class CustomDesign {
    private PDO $db;
    
    // Directory for storing design uploads
    private const UPLOAD_DIR = 'public/images/designs/uploads';
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Public accessor for the per-design random folder token. Used by the
     * preview-saving controller so it stores files under the same folder
     * as element uploads.
     */
    public function getPathToken(int $designId): string {
        return $this->pathTokenFor($designId);
    }

    /**
     * Return the per-design random folder token, creating and persisting one
     * if the row doesn't have it yet. Folder names built from this token
     * replace the old predictable {designId} folder names.
     */
    private function pathTokenFor(int $designId): string {
        try {
            $stmt = $this->db->prepare("SELECT path_token FROM custom_designs WHERE id = ?");
            $stmt->execute([$designId]);
            $token = $stmt->fetchColumn();
            if (is_string($token) && strlen($token) === 32 && ctype_xdigit($token)) {
                return $token;
            }
        } catch (PDOException $e) {
            // path_token column may not exist yet (migration not run). Fall back
            // to the legacy id-based folder so the app keeps working.
            return (string)$designId;
        }

        try {
            $token = bin2hex(random_bytes(16));
            $upd = $this->db->prepare("UPDATE custom_designs SET path_token = ? WHERE id = ? AND (path_token IS NULL OR path_token = '')");
            $upd->execute([$token, $designId]);
            // Re-read in case another request raced us.
            $stmt = $this->db->prepare("SELECT path_token FROM custom_designs WHERE id = ?");
            $stmt->execute([$designId]);
            $persisted = $stmt->fetchColumn();
            return is_string($persisted) && $persisted !== '' ? $persisted : $token;
        } catch (PDOException $e) {
            return (string)$designId;
        }
    }

    // Save a design draft array from session and return new design ID
    public static function saveDraft(array $draft): ?int {
        // You may want to adjust these values based on your session structure
        $db = require(__DIR__ . '/../../config/database.php');
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) return null;
        $name = 'Custom Design ' . date('Y-m-d H:i');
        $productId = $draft[0]['product_id'] ?? null;
        $sizeId = $draft[0]['size_id'] ?? null;
        $colorId = $draft[0]['color_id'] ?? null;
        $elements = $draft;
        $colorHex = $draft[0]['color_hex'] ?? null;
        $stmt = $db->prepare("INSERT INTO custom_designs (name, user_id, product_id, size_id, color_id, elements_json, color_hex, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $ok = $stmt->execute([
            $name,
            $userId,
            $productId,
            $sizeId,
            $colorId,
            json_encode($elements),
            $colorHex
        ]);
        if ($ok) {
            return (int)$db->lastInsertId();
        } else {
            error_log('CustomDesign saveDraft error: ' . print_r($stmt->errorInfo(), true));
        }
        return null;
    }
    
    /**
     * Save a custom design with proper image storage
     * Images are extracted from base64, saved as files, and referenced in the database
     */
    public function save(array $data, int $userId): ?int {
        $this->db->beginTransaction();
        
        try {
            $elements = $data['elements'] ?? [];
            $email = $data['email'] ?? null;
            
            // Prepare elements for JSON storage (without base64 data)
            $elementsForJson = [];
            $imageElements = [];
            $textElements = [];
            
            foreach ($elements as $index => $element) {
                $elementCopy = $element;
                $elementCopy['layer_order'] = $index;
                
                if ($element['type'] === 'image') {
                    // Store image element for separate processing
                    $imageElements[] = $elementCopy;
                    
                    // Remove base64 src from JSON storage (will be stored as file)
                    if (isset($elementCopy['src']) && strpos($elementCopy['src'], 'data:image') === 0) {
                        $elementCopy['src_type'] = 'uploaded';
                        $elementCopy['src'] = null; // Will be replaced with file path
                    }
                    
                    // Also remove _original if it contains base64
                    if (isset($elementCopy['_original']['src']) && strpos($elementCopy['_original']['src'], 'data:image') === 0) {
                        unset($elementCopy['_original']['src']);
                    }
                } elseif ($element['type'] === 'text') {
                    $textElements[] = $elementCopy;
                }
                
                $elementsForJson[] = $elementCopy;
            }
            
            // Add email + editor design-area metadata
            $elementsWithMeta = [
                '_meta' => [
                    'email'        => $email,
                    'editorDAWidth'  => isset($data['editorDAWidth'])  ? (float)$data['editorDAWidth']  : null,
                    'editorDAHeight' => isset($data['editorDAHeight']) ? (float)$data['editorDAHeight'] : null,
                ]
            ] + $elementsForJson;
            
            // Insert main design record
            $stmt = $this->db->prepare("
                INSERT INTO custom_designs (name, user_id, product_id, size_id, color_id, elements_json, color_hex, email, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $ok = $stmt->execute([
                $data['name'],
                $userId,
                $data['product_id'],
                $data['size_id'] ?? null,
                $data['color_id'] ?? null,
                json_encode($elementsWithMeta),
                $data['color_hex'] ?? null,
                $email
            ]);
            
            if (!$ok) {
                throw new Exception('Failed to insert design: ' . print_r($stmt->errorInfo(), true));
            }
            
            $designId = (int)$this->db->lastInsertId();
            
            // Process and save image elements
            foreach ($imageElements as $index => $element) {
                $this->saveImageElement($designId, $element, $index);
            }
            
            // Save text elements
            foreach ($textElements as $index => $element) {
                $this->saveTextElement($designId, $element, $index);
            }
            
            $this->db->commit();
            return $designId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('CustomDesign save error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update an existing custom design
     */
    public function update(array $data, int $userId): bool {
        $this->db->beginTransaction();
        
        try {
            $designId = (int)$data['design_id'];
            $elements = $data['elements'] ?? [];
            $email = $data['email'] ?? null;
            
            // Prepare elements for JSON storage (without base64 data)
            $elementsForJson = [];
            $imageElements = [];
            $textElements = [];
            
            foreach ($elements as $index => $element) {
                $elementCopy = $element;
                $elementCopy['layer_order'] = $index;
                
                if ($element['type'] === 'image') {
                    $imageElements[] = $elementCopy;
                    
                    if (isset($elementCopy['src']) && strpos($elementCopy['src'], 'data:image') === 0) {
                        $elementCopy['src_type'] = 'uploaded';
                        $elementCopy['src'] = null;
                    }
                    
                    if (isset($elementCopy['_original']['src']) && strpos($elementCopy['_original']['src'], 'data:image') === 0) {
                        unset($elementCopy['_original']['src']);
                    }
                } elseif ($element['type'] === 'text') {
                    $textElements[] = $elementCopy;
                }
                
                $elementsForJson[] = $elementCopy;
            }
            
            $elementsWithMeta = [
                '_meta' => [
                    'email'          => $email,
                    'editorDAWidth'  => isset($data['editorDAWidth'])  ? (float)$data['editorDAWidth']  : null,
                    'editorDAHeight' => isset($data['editorDAHeight']) ? (float)$data['editorDAHeight'] : null,
                ]
            ] + $elementsForJson;

            // Update main design record
            $stmt = $this->db->prepare("
                UPDATE custom_designs 
                SET elements_json = ?, color_hex = ?, email = ?, updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            
            $ok = $stmt->execute([
                json_encode($elementsWithMeta),
                $data['color_hex'] ?? null,
                $email,
                $designId,
                $userId
            ]);
            
            if (!$ok) {
                throw new Exception('Failed to update design: ' . print_r($stmt->errorInfo(), true));
            }
            
            // Delete old uploads and texts (we'll re-add them)
            $this->db->prepare("DELETE FROM custom_design_uploads WHERE design_id = ?")->execute([$designId]);
            $this->db->prepare("DELETE FROM custom_design_texts WHERE design_id = ?")->execute([$designId]);
            
            // Process and save image elements
            foreach ($imageElements as $index => $element) {
                $this->saveImageElement($designId, $element, $index);
            }
            
            // Save text elements
            foreach ($textElements as $index => $element) {
                $this->saveTextElement($designId, $element, $index);
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('CustomDesign update error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Save an image element - extract base64 to file and store metadata
     */
    private function saveImageElement(int $designId, array $element, int $order): void {
        $storedFilePath = null;
        $fileSize = 0;
        $mimeType = 'image/png';
        
        // Extract and save base64 image to file
        if (!empty($element['src']) && strpos($element['src'], 'data:image') === 0) {
            $result = $this->saveBase64Image($element['src'], $designId, $element['id'] ?? 'element-' . $order);
            if ($result) {
                $storedFilePath = $result['path'];
                $fileSize = $result['size'];
                $mimeType = $result['mime_type'];
            }
        } elseif (!empty($element['src'])) {
            // Existing file path (not base64) - preserve it
            $srcPath = $element['src'];
            // Remove leading slash if present for storage
            if (strpos($srcPath, '/') === 0) {
                $srcPath = substr($srcPath, 1);
            }
            // If it doesn't start with public/, add it for consistency with save
            if (strpos($srcPath, 'public/') !== 0 && strpos($srcPath, 'images/') === 0) {
                $srcPath = 'public/' . $srcPath;
            }
            $storedFilePath = $srcPath;
            
            // Try to get file size from actual file
            $fullPath = __DIR__ . '/../../' . $storedFilePath;
            if (file_exists($fullPath)) {
                $fileSize = filesize($fullPath);
            }
        }
        
        // Determine view placement
        $view = $element['view'] ?? 'front';
        $validViews = ['front', 'back', 'left-sleeve', 'right-sleeve'];
        if (!in_array($view, $validViews)) {
            $view = 'front';
        }
        
        // Check if custom_design_uploads table exists
        try {
            $stmt = $this->db->prepare("
                INSERT INTO custom_design_uploads 
                (design_id, element_id, stored_file_path, file_size, mime_type, view_placement, 
                 position_x, position_y, width, height, rotation, is_flipped, color_overlay, bg_removed, layer_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $designId,
                $element['id'] ?? 'element-' . $order,
                $storedFilePath,
                $fileSize,
                $mimeType,
                $view,
                $element['x'] ?? 0,
                $element['y'] ?? 0,
                $element['width'] ?? 80,
                $element['height'] ?? 80,
                $element['rotation'] ?? 0,
                isset($element['flipped']) && $element['flipped'] ? 1 : 0,
                $element['color'] ?? null,
                isset($element['bgRemoved']) && $element['bgRemoved'] ? 1 : 0,
                $element['layer_order'] ?? $order
            ]);
        } catch (PDOException $e) {
            // Table may not exist yet - log but continue
            error_log('custom_design_uploads table may not exist: ' . $e->getMessage());
        }
    }
    
    /**
     * Save a text element to the database
     */
    private function saveTextElement(int $designId, array $element, int $order): void {
        // Determine view placement
        $view = $element['view'] ?? 'front';
        $validViews = ['front', 'back', 'left-sleeve', 'right-sleeve'];
        if (!in_array($view, $validViews)) {
            $view = 'front';
        }
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO custom_design_texts
                (design_id, element_id, text_content, font_family, font_size, text_color,
                 is_bold, is_italic, is_underline, view_placement, position_x, position_y, layer_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $designId,
                $element['id'] ?? 'element-' . $order,
                $element['text'] ?? '',
                $element['fontFamily'] ?? 'Arial, sans-serif',
                $element['fontSize'] ?? 24,
                $element['color'] ?? '#000000',
                isset($element['bold']) && $element['bold'] ? 1 : 0,
                isset($element['italic']) && $element['italic'] ? 1 : 0,
                isset($element['underline']) && $element['underline'] ? 1 : 0,
                $view,
                $element['x'] ?? 0,
                $element['y'] ?? 0,
                $element['layer_order'] ?? $order
            ]);
        } catch (PDOException $e) {
            // Table may not exist yet - log but continue
            error_log('custom_design_texts table may not exist: ' . $e->getMessage());
        }
    }
    
    /**
     * Save base64 image data to a file
     */
    private function saveBase64Image(string $base64Data, int $designId, string $elementId): ?array {
        // Only allow real raster image types. Notably this rejects SVG (which can
        // carry <script> and become stored XSS when served inline) and anything
        // masquerading as an image with a dangerous extension like .php/.phtml.
        static $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];

        // Parse base64 data
        if (preg_match('/^data:image\/(png|jpe?g|gif|webp);base64,/i', $base64Data)) {
            $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
        } else {
            return null;
        }

        $decodedData = base64_decode($base64Data, true);
        if ($decodedData === false || $decodedData === '') {
            return null;
        }

        if (strlen($decodedData) > Upload::DEFAULT_MAX_BYTES) {
            return null;
        }

        // Confirm the bytes really are one of the allowlisted image types, and
        // derive the extension from the detected content — never from the label.
        $info = @getimagesizefromstring($decodedData);
        if (!is_array($info) || empty($info['mime'])) {
            return null;
        }
        $mime = strtolower((string)$info['mime']);
        if (!isset($mimeToExt[$mime])) {
            return null;
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $finfoMime = strtolower((string)finfo_buffer($finfo, $decodedData));
                finfo_close($finfo);
                if ($finfoMime !== '' && $finfoMime !== $mime) {
                    return null;
                }
            }
        }

        // Create upload directory if it doesn't exist. Folder name is the
        // random path_token rather than the predictable design id.
        $folder = $this->pathTokenFor($designId);
        $uploadDir = __DIR__ . '/../../' . self::UPLOAD_DIR;
        $designDir = $uploadDir . '/' . $folder;

        if (!is_dir($designDir)) {
            mkdir($designDir, 0755, true);
        }

        // Generate filename: random hex prefix + sanitized element id keeps
        // the file unguessable while still being human-readable in the DB.
        // The extension comes from the content-verified MIME, not user input.
        $extension = $mimeToExt[$mime];
        $safeElement = preg_replace('/[^A-Za-z0-9_-]/', '_', $elementId);
        $filename = bin2hex(random_bytes(8)) . '_' . $safeElement . '.' . $extension;
        $fullPath = $designDir . '/' . $filename;
        $relativePath = self::UPLOAD_DIR . '/' . $folder . '/' . $filename;

        // Save file
        $bytesWritten = file_put_contents($fullPath, $decodedData);
        if ($bytesWritten === false) {
            return null;
        }
        @chmod($fullPath, 0644);

        return [
            'path' => $relativePath,
            'size' => $bytesWritten,
            'mime_type' => $mime
        ];
    }
    
    /**
     * Get a design by ID with all its elements
     */
    public function getById(int $designId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM custom_designs WHERE id = ?");
        $stmt->execute([$designId]);
        $design = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$design) {
            return null;
        }
        
        // Get image elements
        try {
            $stmt = $this->db->prepare("SELECT * FROM custom_design_uploads WHERE design_id = ? ORDER BY layer_order");
            $stmt->execute([$designId]);
            $design['uploads'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $design['uploads'] = [];
        }
        
        // Get text elements
        try {
            $stmt = $this->db->prepare("SELECT * FROM custom_design_texts WHERE design_id = ? ORDER BY layer_order");
            $stmt->execute([$designId]);
            $design['texts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $design['texts'] = [];
        }
        
        return $design;
    }
    
    /**
     * Get all designs for a user
     */
    public function getByUserId(int $userId): array {
        $stmt = $this->db->prepare("SELECT * FROM custom_designs WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Delete a design and all its associated data (uploads, texts, files)
     */
    public function delete(int $designId): bool {
        $this->db->beginTransaction();
        
        try {
            // Get uploads to delete the actual files
            $stmt = $this->db->prepare("SELECT stored_file_path FROM custom_design_uploads WHERE design_id = ?");
            $stmt->execute([$designId]);
            $uploads = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Delete the physical files
            foreach ($uploads as $upload) {
                if (!empty($upload['stored_file_path'])) {
                    $filePath = __DIR__ . '/../../' . $upload['stored_file_path'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
            }
            
            // Try to remove both the legacy id-named folder and the new
            // token-named folder (whichever happens to exist for this design).
            foreach ([$this->pathTokenFor($designId), (string)$designId] as $folder) {
                $designDir = __DIR__ . '/../../' . self::UPLOAD_DIR . '/' . $folder;
                if (is_dir($designDir)) {
                    @rmdir($designDir); // Will only remove if empty
                }
            }
            
            // Delete from custom_design_uploads
            $this->db->prepare("DELETE FROM custom_design_uploads WHERE design_id = ?")->execute([$designId]);
            
            // Delete from custom_design_texts
            $this->db->prepare("DELETE FROM custom_design_texts WHERE design_id = ?")->execute([$designId]);
            
            // Delete the main design record
            $stmt = $this->db->prepare("DELETE FROM custom_designs WHERE id = ?");
            $stmt->execute([$designId]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('CustomDesign delete error: ' . $e->getMessage());
            return false;
        }
    }
}

