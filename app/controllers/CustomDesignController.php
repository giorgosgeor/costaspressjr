<?php
class CustomDesignController extends Controller {
    // POST /custom-design/save
    public function save(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['name'])) {
            http_response_code(400);
            echo 'Missing design name or data';
            return;
        }

        // Two kinds of save share this endpoint:
        //  - The "Save Design" library feature: accounts only. Guests get the
        //    requireLogin response and the studio redirects them to log in.
        //  - cart_flow: the internal save that backs Add to Cart (cart items
        //    reference a design row). Guests may buy without an account, so
        //    this path runs under the session's guest user row.
        if (!Auth::check() && empty($data['cart_flow'])) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['requireLogin' => true, 'redirect' => '/login']);
            return;
        }
        // Counted before any guest row is created, so that is capped too.
        if (!WriteLimit::allow($this->db, 'design')) {
            WriteLimit::refuse();
            return;
        }
        if (Auth::check()) {
            $userId = Auth::userId();
        } else {
            $userId = Auth::effectiveUserId($this->db, true);
            if (!$userId) {
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode(['error' => 'Could not start a shopping session. Please try again.']);
                return;
            }
        }
        $customDesignModel = new \CustomDesign($this->db);
        if ($this->refuseOverStorage($customDesignModel, $userId, $data, true)) {
            return;
        }
        $designId = $customDesignModel->save($data, $userId);
        if ($designId) {
            header('Content-Type: application/json');
            echo json_encode(['id' => $designId]);
        } else {
            http_response_code(500);
            echo 'Failed to save design.';
        }
    }
    
    // POST /custom-design/update
    public function update(): void {
        if (!Auth::check()) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['requireLogin' => true, 'redirect' => '/login']);
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['design_id'])) {
            http_response_code(400);
            echo 'Missing design_id';
            return;
        }
        $userId = Auth::userId();
        $customDesignModel = new \CustomDesign($this->db);
        
        // Verify user owns this design
        $existingDesign = $customDesignModel->getById((int)$data['design_id']);
        if (!$existingDesign || $existingDesign['user_id'] != $userId) {
            http_response_code(403);
            echo 'Not authorized to update this design';
            return;
        }
        if (!WriteLimit::allow($this->db, 'design')) {
            WriteLimit::refuse();
            return;
        }
        if ($this->refuseOverStorage($customDesignModel, $userId, $data, false)) {
            return;
        }

        $success = $customDesignModel->update($data, $userId);
        if ($success) {
            header('Content-Type: application/json');
            echo json_encode(['id' => $data['design_id'], 'updated' => true]);
        } else {
            http_response_code(500);
            echo 'Failed to update design.';
        }
    }
    
    // POST /custom-design/save-previews
    public function savePreviews(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['design_id']) || empty($data['previews'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Missing design_id or previews']);
            return;
        }

        $designId = (int)$data['design_id'];
        // Guests own cart_flow designs; the ownership check below is what
        // actually gates access, for accounts and guests alike.
        $userId = Auth::effectiveUserId($this->db, false);
        if (!$userId) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['requireLogin' => true, 'redirect' => '/login']);
            return;
        }
        
        // Verify ownership
        $stmt = $this->db->prepare("SELECT id, user_id FROM custom_designs WHERE id = ?");
        $stmt->execute([$designId]);
        $design = $stmt->fetch();
        
        if (!$design || $design['user_id'] != $userId) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Not authorized']);
            return;
        }

        if (!WriteLimit::allow($this->db, 'preview')) {
            WriteLimit::refuse();
            return;
        }

        $customDesignModel = new \CustomDesign($this->db);

        // Folder name is the design's random path_token, falling back to the
        // legacy {designId} folder for installs that haven't run the token
        // migration yet (pathTokenFor handles that).
        $folder = $customDesignModel->getPathToken((int)$designId);
        $previewPaths = PreviewImages::store($folder, (array)$data['previews']);

        // Update custom_designs with preview paths
        if (!empty($previewPaths)) {
            $stmt = $this->db->prepare("UPDATE custom_designs SET preview_images = ? WHERE id = ?");
            $stmt->execute([json_encode($previewPaths), $designId]);
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'previews' => $previewPaths]);
    }
    
    // POST /custom-design/delete
    public function delete(): void {
        if (!Auth::check()) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['requireLogin' => true, 'redirect' => '/login']);
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['design_id'])) {
            http_response_code(400);
            echo 'Missing design_id';
            return;
        }
        $userId = Auth::userId();
        $customDesignModel = new \CustomDesign($this->db);
        
        // Verify user owns this design
        $existingDesign = $customDesignModel->getById((int)$data['design_id']);
        if (!$existingDesign || $existingDesign['user_id'] != $userId) {
            http_response_code(403);
            echo 'Not authorized to delete this design';
            return;
        }
        // An ordered design is kept for printing; one in the cart, for the cart.
        $usedBy = $customDesignModel->usedBy((int)$data['design_id']);
        if ($usedBy !== null) {
            http_response_code(409);
            echo I18n::t($usedBy === 'order' ? 'account.delete_in_order' : 'account.delete_in_cart');
            return;
        }

        $success = $customDesignModel->delete((int)$data['design_id']);
        if ($success) {
            header('Content-Type: application/json');
            echo json_encode(['deleted' => true]);
        } else {
            http_response_code(500);
            echo 'Failed to delete design.';
        }
    }

    /**
     * Refuse, with a 413 the studio shows as a message, a save that would take
     * the user past what one person may keep: MAX_DESIGNS_PER_USER designs
     * ($isNew only) or MAX_UPLOAD_BYTES_PER_USER of uploaded images. Only new
     * image data counts, as if none of it were stored yet; a save without any
     * (text, or images already uploaded) is never refused for space.
     */
    private function refuseOverStorage(CustomDesign $model, int $userId, array $data, bool $isNew): bool {
        $incoming = CustomDesign::incomingBytes($data['elements'] ?? []);
        if ($isNew && $model->countFor($userId) >= CustomDesign::MAX_DESIGNS_PER_USER) {
            $error = I18n::t('limit.too_many_designs', ['count' => CustomDesign::MAX_DESIGNS_PER_USER]);
        } elseif ($incoming > 0 && $model->uploadedBytesOf($userId) + $incoming > CustomDesign::MAX_UPLOAD_BYTES_PER_USER) {
            $error = I18n::t('limit.storage_full', ['mb' => intdiv(CustomDesign::MAX_UPLOAD_BYTES_PER_USER, 1024 * 1024)]);
        } else {
            return false;
        }
        http_response_code(413);
        header('Content-Type: application/json');
        echo json_encode(['error' => $error]);
        return true;
    }
}
