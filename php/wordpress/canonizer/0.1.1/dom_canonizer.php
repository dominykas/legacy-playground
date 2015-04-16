<?php
/*
Plugin Name: Dom's Canonizer
Plugin URI: http://www.dominykas.com/plugins/#
Description: Proper URL rewriting & canonizing rules. USE WITH CAUTION! SEE COMMENT!
Author: Dominykas Blyze
Version: 0.1.1
Author URI: http://www.dominykas.com/
*/

/**
 * Disclaimer
 *
 * This plugin is by no means in release condition
 * It was tailored to my needs
 * It WILL BREAK your links if you do not take precautions and analyze how it works
 * It presumes several conditions, i.e. certain permalink structure, posts page first, etc
 * It might not work on PHP4
 * The licence is Creative Commons attribution, but you MUST leave this warning or
 * take all the responsibility in case something goes wrong
 * I do not take responsibility in case something goes wrong - do not use this plugin in production
 *
 * @TODO
 *   proper PHP5 with static calls n stuff
 *   comments_popup_link [$wpcommentsjavascript]
 *   wp_get_archives [weekly]
 *   get_category_rss_link & get_author_rss_link are bloody outdated
 *   get_tag_feed_link has a bloody problem with trailing slashes
 */

add_action('init',array('Dominykas_Canonizer','init'));

class Dominykas_Canonizer
{

    function init()
    {
        global $wp_rewrite;

        //disable default canonical redirection, use our own
        remove_action('template_redirect', 'redirect_canonical');
        add_action('template_redirect', array('Dominykas_Canonizer','redirect_canonical'));


        // set some permalink structures
        $wp_rewrite->page_structure = '%pagename%.html';
        $wp_rewrite->category_structure = '/category/%category%.html';
        $wp_rewrite->tag_structure = '/tag/%tag%.html';

        // page rewriting / canonizing, attachment rewriting
        add_filter('page_rewrite_rules',array('Dominykas_Canonizer','f_rr_page'));

        // attachment canonizing
        add_filter('attachment_link',array('Dominykas_Canonizer','f_url_attachment'),10,2);

        // post rewriting / canonizing
        add_filter('post_rewrite_rules',array('Dominykas_Canonizer','f_rr_post'));
        add_filter('trackback_url',array('Dominykas_Canonizer','f_url_trackback'));
        add_filter('post_comments_feed_link',array('Dominykas_Canonizer','f_url_post_comments'));

        // date rewriting / canonizing
        add_filter('date_rewrite_rules',array('Dominykas_Canonizer','f_rr_date'));
        add_filter('day_link',array('Dominykas_Canonizer','f_url_date_d'),10,4);
        add_filter('month_link',array('Dominykas_Canonizer','f_url_date_m'),10,3);
        add_filter('year_link',array('Dominykas_Canonizer','f_url_date_y'),10,2);

        // feed rewriting / canonizing
        add_filter('root_rewrite_rules',array('Dominykas_Canonizer','f_rr_root'));
        add_filter('feed_link',array('Dominykas_Canonizer','f_url_feed'),10,2);
        add_filter('comments_rewrite_rules',array('Dominykas_Canonizer','f_rr_comments'));

        add_filter('search_rewrite_rules',array('Dominykas_Canonizer','f_rr_search'));

        // category&tag rewriting / canonizing
        add_filter('category_rewrite_rules',array('Dominykas_Canonizer','f_rr_category'));
        add_filter('category_link',array('Dominykas_Canonizer','f_url_category'),10,2);
        add_filter('category_feed_link',array('Dominykas_Canonizer','f_url_category_feed'));
        add_filter('tag_rewrite_rules',array('Dominykas_Canonizer','f_rr_tag'));
        add_filter('tag_link',array('Dominykas_Canonizer','f_url_tag'),10,2);
        add_filter('tag_feed_link',array('Dominykas_Canonizer','f_url_tag_feed'),10,2);

        //author rewriting / canonizing
        add_filter('author_rewrite_rules',array('Dominykas_Canonizer','f_rr_author'));
        add_filter('author_link',array('Dominykas_Canonizer','f_url_author'),10,3);
        add_filter('author_feed_link',array('Dominykas_Canonizer','f_url_author_feed'),10,3);

        add_filter('rewrite_rules_array',array('Dominykas_Canonizer','f_rr'));
        add_filter('clean_url',array('Dominykas_Canonizer','f_url'),10,3);

        $wp_rewrite->flush_rules();

    }

    // redirect to our defined url
    function redirect_canonical()
    {
    	global $wp_rewrite, $posts, $is_IIS, $wpdb;

        if (is_404() || is_admin() || is_preview() || ( isset($_POST) && count($_POST) ) || $is_IIS) {
            return;
        }
        /*
    	if (is_search() || is_comments_popup() )
    		return;
    		*/

    	if ( !$requested_url ) {
    		// build the URL in the address bar
    		$requested_url  = ( isset($_SERVER['HTTPS'] ) && strtolower($_SERVER['HTTPS']) == 'on' ) ? 'https://' : 'http://';
    		$requested_url .= $_SERVER['HTTP_HOST'];
    		$requested_url .= $_SERVER['REQUEST_URI'];
    	}

    	$original = @parse_url($requested_url);
    	if ( false === $original )
    		return;

    	// Some PHP setups turn requests for / into /index.php in REQUEST_URI
    	$original['path'] = preg_replace('|/index\.php$|', '/', $original['path']);

        global $wp;
        $qa=$wp->query_vars;
        foreach ($qa as $k => $v) if (empty($v)) unset($qa[$k]);

        // handle pages, posts and attachments
        // all of them can take one of True Forms:
        //   trackback, html display, one of the feeds
        // attachment can as well come as download, and everything else might probably
        // come in other flavors - e.g. wml, but that is @far-future-todo
        if (is_singular()) {
            // @todo: paged viewing (whenever the hell that is supposed to happen)
            if (is_feed()) {
                $canonical_url = get_post_comments_feed_link('',get_query_var('feed'));
            } elseif (is_trackback()) {
                $canonical_url = get_trackback_url();
            } else {
                $canonical_url = get_permalink();
            }

        // handle robots.txt
        } elseif (is_robots()) {
            $canonical_url=get_option('home').'/robots.txt';

        // handle everything else
        } else {

            //for my purposes, only if month/year exist in query
            //and none other args - rewrite to canonical
            if (query_allowed_only($qa,array('year','monthnum'))) {
                $canonical_url = get_month_link(get_query_var('year'),get_query_var('monthnum'));

            } elseif (query_allowed_only($qa,array('year'))) {
                $canonical_url = get_year_link(get_query_var('year'));

            // check feed vars
            } elseif (query_allowed_only($qa,array('feed'))) {
                $canonical_url = get_feed_link(get_query_var('feed'));

            // check comment feed vars
            } elseif (query_allowed_only($qa,array('feed','withcomments'))) {
                $canonical_url = get_feed_link('comments_'.get_query_var('feed'));

            // category alone
            } elseif (query_allowed_only($qa,array('cat'))) {
                $category = &get_category(get_query_var('cat'));
                if (!empty($category->term_id)) {
                    $canonical_url = get_category_link($category->term_id);
                }

            } elseif (query_allowed_only($qa,array('category_name'))) {
                $cat=get_category_by_path(get_query_var('category_name'),false);
                if (!empty($cat)) {
                    $canonical_url = get_category_link($cat->term_id);
                }

            // category feed
            } elseif (query_allowed_only($qa,array('cat','feed'))) {
                $category = &get_category(get_query_var('cat'));
                if (!empty($category->term_id)) {
                    $canonical_url = get_category_rss_link(false,$category->term_id,$category->slug);
                }

            } elseif (query_allowed_only($qa,array('category_name','feed'))) {
                $cat=get_category_by_path(get_query_var('category_name'),false);
                if (!empty($cat)) {
                    $canonical_url = get_category_rss_link(false,$cat->term_id,$cat->slug);
                }

            // tag alone
            } elseif (query_allowed_only($qa,array('tag'))) {
            	$tag = get_term_by('slug',get_query_var('tag'),'post_tag');
            	if (!empty($tag->term_id)) {
                    $canonical_url = get_tag_link($tag->term_id);
                }

            // tag feed
            } elseif (query_allowed_only($qa,array('tag','feed'))) {
            	$tag = get_term_by('slug',get_query_var('tag'),'post_tag');
            	if (!empty($tag->term_id)) {
                    $canonical_url = get_tag_feed_link($tag->term_id,get_query_var('feed'));
                }

            // author alone
            } elseif (query_allowed_only($qa,array('author'))) {
                $canonical_url = get_author_posts_url(get_query_var('author'));

            } elseif (query_allowed_only($qa,array('author_name'))) {
			    $author_name = sanitize_title(get_query_var('author_name'));
			    $author = $wpdb->get_var("SELECT ID FROM $wpdb->users WHERE user_nicename='".$author_name."'");
			    if (!empty($author)) {
                    $canonical_url = get_author_posts_url($author);
                }

            // author feed
            } elseif (query_allowed_only($qa,array('author','feed'))) {
                $canonical_url = get_author_rss_link(false,get_query_var('author'),'');

            } elseif (query_allowed_only($qa,array('author_name','feed'))) {
			    $author_name = sanitize_title(get_query_var('author_name'));
			    $author = $wpdb->get_var("SELECT ID FROM $wpdb->users WHERE user_nicename='".$author_name."'");
			    if (!empty($author)) {
                    $canonical_url = get_author_rss_link(false,$author,'');
                }

            }

        }
        if (empty($canonical_url)) {
            if (!is_home() || get_query_var('paged') || get_query_var('search')) {
                $canonical_url = get_option('home').'/search?'.build_query($qa);
            } else {
                $canonical_url = get_option('home').'/';
            }
        }
        $canonical=parse_url($canonical_url);

        if (!empty($canonical) && $canonical != $original) {
   			wp_redirect(rebuild_url($canonical), 301);
  			exit();
        }
    }

    // filter, url, attachment
    function f_url_attachment($url,$id=false)
    {
    	if (! $id) {
    		$id = (int) $post->ID;
    	}
    	$object = get_post($id);

        $unixtime = strtotime($object->post_date);
        $date = explode(" ",date('Y m d H i s', $unixtime));

        $url=get_option('home').'/attachments/'.$date[0].'/'.$date[1].'/'.$object->post_name.'.html';
        return $url;
    }

    // filter, url, trackback
    function f_url_trackback($tb_url)
    {
        // default wp behaviour is postname/trackback[/]
        // by our definition - ".html" is at the very end of url in permalink
        // so we just replace .html/trackback[/] to .trackback and voila
        $tb_url=preg_replace('#\.html/trackback/?#','.trackback',$tb_url);
        return $tb_url;
    }

    // filter, url, post comments
    function f_url_post_comments($url)
    {
        // default wp behaviour is postname/(feed|rss2|rss|atom|rdf)[/]
        // by our definition - ".html" is at the very end of url in permalink
        // so we just replace .html/(feed|rss2|rss|atom|rdf)[/] to .(rss2|rss|atom|rdf) and voila
        $url=preg_replace('#\.(html/(feed/?)?)(rss2|rss|atom|rdf)/?#','.$3.xml',$url);
        $url=preg_replace('#\.html/feed/?$#','.rss2.xml',$url);
        return $url;
    }

    // filter, rewrite rules, page
    function f_rr_page($rules)
    {
        // mimmic the way wp does things - before a more optimal solution can be created
        $rules=array();
        $uris = get_option('page_uris');
		if( is_array( $uris ) ) {
			foreach ($uris as $uri => $pagename) {
                $add=array(
                    '('.$uri.')\.html$' =>
                        'index.php?pagename=$matches[1]',
                    '('.$uri.')\.(rss|atom|rdf|rss2)\.xml$' =>
                        'index.php?pagename=$matches[1]&feed=$matches[2]',
                    '('.$uri.')\.trackback$' =>
                        'index.php?pagename=$matches[1]&tb=1',
                );
                $rules=array_merge($rules,$add);
            }
        }

        // add attachment rewrites
        $add=array(
            'attachments/([0-9]{4})/([0-9]{1,2})/([^/]+)\.html$' =>
                'index.php?year=$matches[1]&monthnum=$matches[2]&attachment=$matches[3]',
            'attachments/([0-9]{4})/([0-9]{1,2})/([^/]+)\.(rss|atom|rdf|rss2)\.xml$' =>
                'index.php?year=$matches[1]&monthnum=$matches[2]&attachment=$matches[3]&feed=$matches[4]',
            'attachments/([0-9]{4})/([0-9]{1,2})/([^/]+)\.trackback$' =>
                'index.php?year=$matches[1]&monthnum=$matches[2]&attachment=$matches[3]&tb=1',
        );
        $rules=array_merge($rules,$add);

        return $rules;
    }

    // filter, rewrite rules, post
    function f_rr_post($rules)
    {
        $rules=array(
            '([0-9]{4})/([0-9]{1,2})/([^/]+)\.html$' =>
                'index.php?year=$matches[1]&monthnum=$matches[2]&name=$matches[3]',
            '([0-9]{4})/([0-9]{1,2})/([^/]+)\.(rss|atom|rdf|rss2)\.xml$' =>
                'index.php?year=$matches[1]&monthnum=$matches[2]&name=$matches[3]&feed=$matches[4]',
            '([0-9]{4})/([0-9]{1,2})/([^/]+)\.trackback$' =>
                'index.php?year=$matches[1]&monthnum=$matches[2]&name=$matches[3]&tb=1',
        );
        return $rules;
    }

    // filter, rewrite rules, post
    // why would anyone need to subscribe to the feed for today? dunno!
    // dunno and don't care - @far-future-todo for now - anyone can use the "search" feeds
    // as well paging for year/month is not needed for my setup
    function f_rr_date($rules)
    {
        $rules=array(
            '([0-9]{4})/([0-9]{1,2})/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]',
            '([0-9]{4})/?$' => 'index.php?year=$matches[1]',
        );
        return $rules;
    }

    function f_url_date_d($url,$y,$m,$d)
    {
        $url=get_option('home').'/search?year='.$y.'&monthnum='.$m.'&day='.$d;
        return $url;
    }

    function f_url_date_m($url,$y,$m)
    {
        $url=get_option('home').'/'.$y.'/'.sprintf('%02d',$m).'/';
        return $url;
    }

    function f_url_date_y($url,$y)
    {
        $url=get_option('home').'/'.$y.'/';
        return $url;
    }

    function f_rr_search($rules)
    {
        $rules=array(
            'search$' => 'index.php',
        );
        return $rules;
    }

    function f_rr_root($rules)
    {
        $rules=array(
            'index.(rdf|rss|rss2|atom)\.xml$' => 'index.php?&feed=$matches[1]',
        );
        return $rules;
    }

    function f_url_feed($url,$feed)
    {
        $url=preg_replace('#((feed/?)?)(rss2|rss|atom|rdf)/?#','index.$3.xml',$url);
        $url=preg_replace('#feed/?$#','index.rss2.xml',$url);
        $url=preg_replace('#comments/index#','comments',$url);
        return $url;
    }

    function f_rr_comments($rules)
    {
        $rules=array(
            'comments.(rdf|rss|rss2|atom)\.xml$' => 'index.php?&feed=$matches[1]&withcomments=1',
        );
        return $rules;
    }

    function f_rr_author($rules)
    {
        $rules=array(
            'author/([^/]+)\.html$' =>
                'index.php?author_name=$matches[1]',
            'author/([^/]+)\.(rss|atom|rdf|rss2)\.xml$' =>
                'index.php?author_name=$matches[1]&feed=$matches[2]',
            'author/([^/]+)\.trackback$' =>
                'index.php?author_name=$matches[1]&tb=1',
        );
        return $rules;
    }

    function f_url_author($link, $author_id, $author_nicename)
    {
        return get_option('home').'/author/'.$author_nicename.'.html';
    }

    function f_url_author_feed($url)
    {
        $url=preg_replace('#\.(html/(feed/?)?)(rss2|rss|atom|rdf)/?#','.$3.xml',$url);
        $url=preg_replace('#\.html/feed/?$#','.rss2.xml',$url);
        return $url;
    }

    function f_rr_category($rules)
    {
        $rules=array(
            'category/([^\.]+)\.html$' =>
                'index.php?category_name=$matches[1]',
            'category/([^\.]+)\.(rss|atom|rdf|rss2)\.xml$' =>
                'index.php?category_name=$matches[1]&feed=$matches[2]',
            'category/([^\.]+)\.trackback$' =>
                'index.php?category_name=$matches[1]&tb=1',
        );
        return $rules;
    }

    function f_url_category($link, $category_id)
    {
        return $link;
    }

    function f_url_category_feed($url)
    {
        $url=preg_replace('#\.(html/(feed/?)?)(rss2|rss|atom|rdf)/?#','.$3.xml',$url);
        $url=preg_replace('#\.html/feed/?$#','.rss2.xml',$url);
        return $url;
    }

    function f_rr_tag($rules)
    {
        $rules=array(
            'tag/([^/]+)\.html$' =>
                'index.php?tag=$matches[1]',
            'tag/([^/]+)\.(rss|atom|rdf|rss2)\.xml$' =>
                'index.php?tag=$matches[1]&feed=$matches[2]',
            'tag/([^/]+)\.trackback$' =>
                'index.php?tag=$matches[1]&tb=1',
        );
        return $rules;
    }

    function f_url_tag($link, $tag_id)
    {
        return $link;
    }

    function f_url_tag_feed($url,$feed)
    {
        $url=preg_replace('#\.(html/?(feed/?)?)(rss2|rss|atom|rdf)/?#','.$3.xml',$url);
        $url=preg_replace('#\.html/?feed/?$#','.rss2.xml',$url);
        return $url;
    }

    function f_rr($rules)
    {
        // cleanup crappy wp-feed.php stuff
        foreach ($rules as $k => $v) {
            if (substr($k,0,3)=='wp-') {
                unset($rules[$k]);
            }
        }
        return $rules;
    }

    function f_url($url, $original_url, $context)
    {
        if (preg_match('#/?page/(\d+)/?\??#',$url,$matches)) {
            $replacement = '?paged='.$matches[1];
            if (strpos($matches[0],'?')!==false) {
                $replacement.=($context=='display' ? '&#038;':'&');
            }
            $url=str_replace($matches[0],$replacement,$url);
        }
        return $url;
    }

}

function rebuild_url($parsed)
{
    $url='';
    if (!empty($parsed['scheme']))
        $url=$parsed['scheme'].'://';
    if (!empty($parsed['host']))
        $url.=$parsed['host'];
    if (!empty($parsed['port']))
        $url.=':'.$parsed['port'];
    if (!empty($parsed['path']))
        $url.=$parsed['path'];
    if (!empty($parsed['query']))
        $url.='?'.$parsed['query'];
    return $url;
}

function query_allowed_only($all,$allowed)
{
    return (count(array_diff($allowed,array_keys($all)))==0 && count(array_diff(array_keys($all),$allowed))==0);
}
?>