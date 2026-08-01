<?php
// Shim → SJ\Content\Sanitizer (src/Content/Sanitizer.php). SECURITY.md SEC-02.
function sj_sanitize_html(string $html): string
{
    return \SJ\Content\Sanitizer::html($html);
}
