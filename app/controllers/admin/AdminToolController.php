<?php

/**
 * Image tools for preparing product and design artwork in the browser.
 */
class AdminToolController extends AdminController {
    // ==================== TOOLS ====================

    public function backgroundRemover(): void {
        $this->requireAdmin();
        $this->render('admin/tools/background_remover');
    }

    public function imageCropper(): void {
        $this->requireAdmin();
        $this->render('admin/tools/image_cropper');
    }
}
