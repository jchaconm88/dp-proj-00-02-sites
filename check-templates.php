<?php
require '/var/www/html/wp-load.php';
$templates = get_block_templates(array(), 'wp_template');
foreach ($templates as $t) {
    echo $t->slug . PHP_EOL;
}
