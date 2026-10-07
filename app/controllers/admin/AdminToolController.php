<?php

/**
 * Image tools for preparing product and design artwork in the browser.
 */
class AdminToolController extends AdminController {
    // ==================== TOOLS ====================

    public function backgroundRemover(): void {
        $this->requireAdmin();
        $this->render('admin/background_remover', get_defined_vars());
    }

    public function imageCropper(): void {
        $this->requireAdmin();
        $this->render('admin/image_cropper', get_defined_vars());
    }
}
