<?php
class CustomDesignController {
    private PDO $db;
    public function __construct(PDO $db) {
        $this->db = $db;
    }

    // POST /custom-design/save
    public function save(): void {
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
        if (!$data || empty($data['name'])) {
            http_response_code(400);
            echo 'Missing design name or data';
            return;
        }
        $userId = Auth::userId();
        require_once __DIR__ . '/../models/CustomDesign.php';
        $customDesignModel = new \CustomDesign($this->db);
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
        require_once __DIR__ . '/../models/CustomDesign.php';
        $customDesignModel = new \CustomDesign($this->db);
        
        // Verify user owns this design
        $existingDesign = $customDesignModel->getById((int)$data['design_id']);
        if (!$existingDesign || $existingDesign['user_id'] != $userId) {
            http_response_code(403);
            echo 'Not authorized to update this design';
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
        if (!$data || empty($data['design_id']) || empty($data['previews'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Missing design_id or previews']);
            return;
        }
        
        $designId = (int)$data['design_id'];
        $userId = Auth::userId();
        
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

        require_once __DIR__ . '/../models/CustomDesign.php';
        $customDesignModel = new \CustomDesign($this->db);

        // Folder name is the design's random path_token, falling back to the
        // legacy {designId} folder for installs that haven't run the token
        // migration yet (pathTokenFor handles that).
        $folder = $customDesignModel->getPathToken((int)$designId);
        $previewDir = __DIR__ . '/../../public/images/designs/previews/' . $folder;
        if (!is_dir($previewDir)) {
            mkdir($previewDir, 0755, true);
        }

        $previewPaths = [];
        $validViews = ['front', 'back', 'left-sleeve', 'right-sleeve', 'front_design'];
        $allowedImageTypes = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

        foreach ($data['previews'] as $view => $base64Data) {
            if (!in_array($view, $validViews)) continue;
            if (empty($base64Data)) continue;

            // Parse base64
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
                $imageType = $matches[1];
                // Validate image type against whitelist
                if (!in_array(strtolower($imageType), $allowedImageTypes)) continue;
                $rawData = base64_decode(substr($base64Data, strpos($base64Data, ',') + 1));
            } else {
                continue;
            }

            if ($rawData === false) continue;

            // Validate that decoded data is actually an image
            $imageInfo = @getimagesizefromstring($rawData);
            if ($imageInfo === false) continue;

            $ext = $imageType === 'jpeg' ? 'jpg' : $imageType;
            $filename = str_replace('-', '_', $view) . '.' . $ext;
            $fullPath = $previewDir . '/' . $filename;
            $relativePath = 'images/designs/previews/' . $folder . '/' . $filename;

            // Delete old preview for this view if exists
            foreach (['png', 'jpg', 'jpeg', 'webp'] as $oldExt) {
                $oldFile = $previewDir . '/' . str_replace('-', '_', $view) . '.' . $oldExt;
                if (file_exists($oldFile)) {
                    @unlink($oldFile);
                }
            }

            if (file_put_contents($fullPath, $rawData) !== false) {
                $previewPaths[$view] = $relativePath;
            }
        }
        
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
        require_once __DIR__ . '/../models/CustomDesign.php';
        $customDesignModel = new \CustomDesign($this->db);
        
        // Verify user owns this design
        $existingDesign = $customDesignModel->getById((int)$data['design_id']);
        if (!$existingDesign || $existingDesign['user_id'] != $userId) {
            http_response_code(403);
            echo 'Not authorized to delete this design';
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
}
