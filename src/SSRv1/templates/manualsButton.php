<?php

/** @var \WEEEOpen\Tarallo\Product $product */
/** @var string $class */
// The site expects urlencode, not rawurlencode
$escaped = urlencode(strtolower($product->getFullName()));
$href = "https://www.manualslib.com/a/$escaped.html"
?>
<a class="<?= $class ?>" href="<?= $href ?>" target="_blank" role="button" rel="noreferrer"><span class="fa fa-book"></span>&nbsp;Manuals</a>
