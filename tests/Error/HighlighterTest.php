<?php

namespace Tests\Unit\Error;

use Phaseolies\Error\Utils\Highlighter;
use PHPUnit\Framework\TestCase;

class HighlighterTest extends TestCase
{
    public function testMakeReturnsNonEmptyStringForPhpCode()
    {
        $result = Highlighter::make('$variable = "hello";');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testMakeWrapsVariableInSpan()
    {
        $result = Highlighter::make('$myVar = 1;');

        $this->assertStringContainsString('text-hl-variable', $result);
    }

    public function testMakeWrapsStringLiteralInSpan()
    {
        $result = Highlighter::make('"hello world"');

        $this->assertStringContainsString('text-hl-literal', $result);
    }

    public function testMakeWrapsNumberInSpan()
    {
        $result = Highlighter::make('42');

        $this->assertStringContainsString('text-hl-number', $result);
    }

    public function testMakeHandlesCodeAlreadyContainingOpenTag()
    {
        $result = Highlighter::make('<?php echo "hello";');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testMakeEscapesHtmlSpecialChars()
    {
        $result = Highlighter::make('$a = $b & $c;');

        $this->assertStringNotContainsString('&c', $result);
    }

    public function testMakeWrapsKeywordInSpan()
    {
        $result = Highlighter::make('return $value;');

        $this->assertStringContainsString('text-hl-keyword', $result);
    }

    public function testMakeWrapsCommentInSpan()
    {
        $result = Highlighter::make('// this is a comment' . "\n" . '$x = 1;');

        $this->assertStringContainsString('text-hl-comment', $result);
    }

    public function testMakeReturnsHtmlWithSpanTags()
    {
        $result = Highlighter::make('$x = 1;');

        $this->assertStringContainsString('<span', $result);
        $this->assertStringContainsString('</span>', $result);
    }

    public function testMakeHandlesEmptyString()
    {
        $result = Highlighter::make('');

        $this->assertIsString($result);
    }

    public function testMakeHandlesFunctionDefinition()
    {
        $result = Highlighter::make('function myFunc() {}');

        $this->assertStringContainsString('text-hl-definition', $result);
    }

    public function testMakeHandlesClassDefinition()
    {
        $result = Highlighter::make('class MyClass {}');

        $this->assertStringContainsString('text-hl-definition', $result);
    }

    public function testMakeHandlesPublicModifier()
    {
        $result = Highlighter::make('public function foo() {}');

        $this->assertStringContainsString('text-hl-modifier', $result);
    }

    public function testMakeHandlesNewKeyword()
    {
        $result = Highlighter::make('$obj = new stdClass();');

        $this->assertStringContainsString('text-hl-keyword', $result);
    }

    public function testMakeDoesNotDoubleEncodeOpenTag()
    {
        $result = Highlighter::make('<?php $x = 1;');

        $this->assertStringNotContainsString('<?php' . "\n" . '<?php', $result);
    }
}
