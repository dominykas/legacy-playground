<?
$file=dirname(__FILE__).$_SERVER['REQUEST_URI'];

if (substr($_SERVER['REQUEST_URI'], -4, 4)=='.php' && strpos('?', $_SERVER['REQUEST_URI'])===false && file_exists($file)) {
    highlight_file($file);
} else {
    header('HTTP/1.0 404 Not Found');
    ?><H1>Not Found</H1><?
}
?>
