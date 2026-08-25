<?php

namespace Tests\Unit\Services\Inbox;

use App\Models\Email;
use App\Services\Inbox\MarkdownBody;
use PHPUnit\Framework\TestCase;

class MarkdownBodyTest extends TestCase
{
    public function test_renders_bold_and_italic_correctly(): void
    {
        $input = "We have reviewed the changes and this is **possible** but not as good as you might *imagine*.";
        $html = MarkdownBody::render($input);

        $this->assertStringContainsString('<strong>possible</strong>', $html);
        $this->assertStringContainsString('<em>imagine</em>', $html);
        $this->assertStringContainsString('<p style="margin:0 0 12px 0;line-height:1.5">', $html);
    }

    public function test_renders_bullet_lists_with_inline_styling(): void
    {
        $input = "Instead I offer follow\n- Let's have green consistent\n- Simplicity is key\n- Buttons will be bold";
        $html = MarkdownBody::render($input);

        $this->assertStringContainsString('<p style="margin:0 0 12px 0;line-height:1.5">Instead I offer follow</p>', $html);
        $this->assertStringContainsString('<ul style="margin:0 0 12px 0;padding-left:24px;list-style-type:disc;">', $html);
        $this->assertStringContainsString('<li style="margin-bottom:4px;line-height:1.5">Let&#039;s have green consistent</li>', $html);
        $this->assertStringContainsString('<li style="margin-bottom:4px;line-height:1.5">Simplicity is key</li>', $html);
        $this->assertStringContainsString('<li style="margin-bottom:4px;line-height:1.5">Buttons will be bold</li>', $html);
    }

    public function test_renders_numbered_lists(): void
    {
        $input = "Steps:\n1. First step\n2. Second step";
        $html = MarkdownBody::render($input);

        $this->assertStringContainsString('<ol style="margin:0 0 12px 0;padding-left:24px;list-style-type:decimal;">', $html);
        $this->assertStringContainsString('<li style="margin-bottom:4px;line-height:1.5">First step</li>', $html);
        $this->assertStringContainsString('<li style="margin-bottom:4px;line-height:1.5">Second step</li>', $html);
    }

    public function test_normalizes_greeting_line_breaks(): void
    {
        $input = "Hi Client 1,<br/>We have reviewed the changes.<br /><br>Thank you!";
        $html = MarkdownBody::render($input);

        $this->assertStringNotContainsString('<br/>', $html);
        $this->assertStringContainsString('Hi Client 1,', $html);
        $this->assertStringContainsString('We have reviewed the changes.', $html);
    }

    public function test_escapes_raw_html_safely(): void
    {
        $input = "<script>alert('xss')</script> and **bold**";
        $html = MarkdownBody::render($input);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
    }

    public function test_renders_links_and_strike_and_inline_code(): void
    {
        $input = "Check [website](https://example.com) and ~~old text~~ and `code block`";
        $html = MarkdownBody::render($input);

        $this->assertStringContainsString('<a href="https://example.com">website</a>', $html);
        $this->assertStringContainsString('<del>old text</del>', $html);
        $this->assertStringContainsString('<code>code block</code>', $html);
    }

    public function test_looks_like_markdown_detection(): void
    {
        $this->assertTrue(MarkdownBody::looksLikeMarkdown("This is **bold** text"));
        $this->assertTrue(MarkdownBody::looksLikeMarkdown("This is *italic* text"));
        $this->assertTrue(MarkdownBody::looksLikeMarkdown("List:\n- Item 1\n- Item 2"));
        $this->assertTrue(MarkdownBody::looksLikeMarkdown("Hi Client,<br/>This is **possible**"));

        // Rich HTML from legacy editor should return false
        $this->assertFalse(MarkdownBody::looksLikeMarkdown("<p>Hello world</p><ul><li>Item</li></ul>"));
        $this->assertFalse(MarkdownBody::looksLikeMarkdown("<div><span style=\"color:red\">Test</span></div>"));
    }

    public function test_full_email_example_from_user(): void
    {
        $input = "Hi Client 1,<br/>We have reviewed the changes you have requested and I would like to inform you that this is something **possible** but it might not look as good as you might *imagine*.

Instead I offer follow
- Let's have the green colour consistent
- Simplicity is the key so let's remove extra colours
- Buttons will be bold and large as you requested

Please let me know what you think?";

        $html = MarkdownBody::render($input);

        $this->assertStringContainsString('<strong>possible</strong>', $html);
        $this->assertStringContainsString('<em>imagine</em>', $html);
        $this->assertStringContainsString('<ul style="margin:0 0 12px 0;padding-left:24px;list-style-type:disc;">', $html);
        $this->assertStringContainsString('<li style="margin-bottom:4px;line-height:1.5">Let&#039;s have the green colour consistent</li>', $html);
        $this->assertStringContainsString('<li style="margin-bottom:4px;line-height:1.5">Simplicity is the key so let&#039;s remove extra colours</li>', $html);
        $this->assertStringContainsString('<li style="margin-bottom:4px;line-height:1.5">Buttons will be bold and large as you requested</li>', $html);
        $this->assertStringContainsString('<p style="margin:0 0 12px 0;line-height:1.5">Please let me know what you think?</p>', $html);
    }

    public function test_is_markdown_with_and_without_draft_meta(): void
    {
        // Case 1: draft_meta explicitly set
        $emailWithMeta = new Email();
        $emailWithMeta->draft_meta = ['body_format' => 'markdown'];
        $emailWithMeta->body = 'Regular text without tokens';
        $this->assertTrue(MarkdownBody::isMarkdown($emailWithMeta));

        // Case 2: draft_meta is null, but body has markdown tokens
        $emailWithoutMeta = new Email();
        $emailWithoutMeta->draft_meta = null;
        $emailWithoutMeta->body = "Hi Client 1,<br/>We have reviewed the changes you have requested and I would like to inform you that this is something **possible** but it might not look as good as you might *imagine*.";
        $this->assertTrue(MarkdownBody::isMarkdown($emailWithoutMeta));

        // Case 3: templated email is never markdown
        $templatedEmail = new Email();
        $templatedEmail->template_id = 5;
        $templatedEmail->body = '**possible**';
        $this->assertFalse(MarkdownBody::isMarkdown($templatedEmail));

        // Case 4: legacy rich HTML email is not markdown
        $legacyHtmlEmail = new Email();
        $legacyHtmlEmail->draft_meta = null;
        $legacyHtmlEmail->body = '<p>We have reviewed the changes</p><ul><li>Item 1</li></ul>';
        $this->assertFalse(MarkdownBody::isMarkdown($legacyHtmlEmail));
    }
}
