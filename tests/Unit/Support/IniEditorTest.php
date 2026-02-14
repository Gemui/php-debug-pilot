<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\IniEditor;
use PHPUnit\Framework\TestCase;

final class IniEditorTest extends TestCase
{
    private IniEditor $editor;

    protected function setUp(): void
    {
        $this->editor = new IniEditor();
    }

    // -----------------------------------------------------------------
    //  isLineEnabled()
    // -----------------------------------------------------------------

    public function testIsLineEnabledReturnsTrueForActiveDirective(): void
    {
        $ini = "zend_extension=xdebug\n";
        $this->assertTrue($this->editor->isLineEnabled($ini, 'zend_extension\s*=\s*xdebug'));
    }

    public function testIsLineEnabledReturnsFalseForCommentedDirective(): void
    {
        $ini = ";zend_extension=xdebug\n";
        $this->assertFalse($this->editor->isLineEnabled($ini, 'zend_extension\s*=\s*xdebug'));
    }

    public function testIsLineEnabledReturnsFalseWhenMissing(): void
    {
        $ini = "; some other config\n";
        $this->assertFalse($this->editor->isLineEnabled($ini, 'zend_extension\s*=\s*xdebug'));
    }

    public function testIsLineEnabledHandlesWhitespaceVariations(): void
    {
        $ini = "  zend_extension = xdebug\n";
        $this->assertTrue($this->editor->isLineEnabled($ini, 'zend_extension\s*=\s*xdebug'));
    }

    // -----------------------------------------------------------------
    //  commentLine()
    // -----------------------------------------------------------------

    public function testCommentLineCommentOutActiveLine(): void
    {
        $ini = "zend_extension=xdebug\n";
        $result = $this->editor->commentLine($ini, 'zend_extension\s*=\s*xdebug');

        $this->assertStringContainsString(';zend_extension=xdebug', $result);
    }

    public function testCommentLineDoesNotDoubleComment(): void
    {
        $ini = ";zend_extension=xdebug\n";
        $result = $this->editor->commentLine($ini, 'zend_extension\s*=\s*xdebug');

        // Should remain single-commented
        $this->assertSame($ini, $result);
    }

    // -----------------------------------------------------------------
    //  uncommentLine()
    // -----------------------------------------------------------------

    public function testUncommentLineRemovesLeadingSemicolon(): void
    {
        $ini = ";zend_extension=xdebug\n";
        $result = $this->editor->uncommentLine($ini, 'zend_extension\s*=\s*xdebug');

        $this->assertStringContainsString('zend_extension=xdebug', $result);
        $this->assertStringNotContainsString(';zend_extension=xdebug', $result);
    }

    public function testUncommentLineHandlesSpaceAfterSemicolon(): void
    {
        $ini = "; zend_extension=xdebug\n";
        $result = $this->editor->uncommentLine($ini, 'zend_extension\s*=\s*xdebug');

        $this->assertStringContainsString('zend_extension=xdebug', $result);
    }

    public function testUncommentLineDoesNothingWhenAlreadyActive(): void
    {
        $ini = "zend_extension=xdebug\n";
        $result = $this->editor->uncommentLine($ini, 'zend_extension\s*=\s*xdebug');

        $this->assertSame($ini, $result);
    }

    // -----------------------------------------------------------------
    //  hasLine()
    // -----------------------------------------------------------------

    public function testHasLineReturnsTrueForActiveLine(): void
    {
        $ini = "zend_extension=xdebug\n";
        $this->assertTrue($this->editor->hasLine($ini, 'zend_extension\s*=\s*xdebug'));
    }

    public function testHasLineReturnsTrueForCommentedLine(): void
    {
        $ini = ";zend_extension=xdebug\n";
        $this->assertTrue($this->editor->hasLine($ini, 'zend_extension\s*=\s*xdebug'));
    }

    public function testHasLineReturnsFalseWhenMissing(): void
    {
        $ini = "; unrelated config\n";
        $this->assertFalse($this->editor->hasLine($ini, 'zend_extension\s*=\s*xdebug'));
    }

    // -----------------------------------------------------------------
    //  appendLine()
    // -----------------------------------------------------------------

    public function testAppendLineAddsNewLine(): void
    {
        $ini = "[php]\n";
        $result = $this->editor->appendLine($ini, 'zend_extension=xdebug');

        $this->assertStringContainsString("zend_extension=xdebug\n", $result);
    }

    public function testAppendLineEnsuresNewlineBeforeAppending(): void
    {
        $ini = "[php]";
        $result = $this->editor->appendLine($ini, 'zend_extension=xdebug');

        $this->assertStringContainsString("\nzend_extension=xdebug\n", $result);
    }
}
