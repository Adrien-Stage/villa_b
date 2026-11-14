<?php

namespace Tests\Feature;

use Tests\TestCase;

class ToastNotificationTest extends TestCase
{
    public function test_toasts_partial_does_not_contain_redundant_x_init(): void
    {
        $content = file_get_contents(resource_path('views/partials/toasts.blade.php'));

        $this->assertStringNotContainsString('x-init="init()"', $content, 'x-init="init()" causes Alpine to execute init() twice.');
        $this->assertStringContainsString('toastNotificationManager()', $content);
        $this->assertStringContainsString('this._initialized = true', $content);
    }
}
