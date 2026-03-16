<?php
// BearBlog Import routine
// place bearblog MD files in a import folder under the content/posts
//

declare(strict_types=1);

require __DIR__ . '/functions.php';
require_setup_redirect();

$config = load_config();
$fontStack = font_stack_css($config['theme']['font_stack'] ?? 'sans');

$error_msg='';
start_admin_session();

if (is_admin_logged_in()):  
    $import_action= $_GET['action'] ?? '';
    $import_force = $_GET['force'] ?? '';
    $delete_file  = $_GET['del'] ?? '';
    $sourcePosts = get_all_import_posts();

    $to_import ='';
    $not_import='';
    if (!count($sourcePosts)) { $error_msg='Nothing found to import.';}

    if ($import_action == 'go'):
        $not_import = save_all_import_posts($sourcePosts, $import_force, $delete_file);
    else:
        foreach ($sourcePosts as $post) {
            if ($post['import_note']==''):
                $to_import .= '<li>' . $post['title'] . '</li>'; 
            else:
                $not_import.='<li>' . $post['import_note'] .' : '.$post['title'].'</li>'; 
            endif;
        }
    endif;
else:
    $error_msg="Login to the admin Dashboard first.";
endif;

function get_all_import_posts(): array
{
    static $cache = null;
    if ($cache === null) {
        if (!is_dir(PUREBLOG_POSTS_PATH . '/import')) {
            $cache = [];
        } else {
            $files = glob(PUREBLOG_POSTS_PATH . '/import/*.md') ?: [];
            $posts = [];

            $config = load_config();
            foreach ($files as $file) {
                $parsed = parse_post_file($file);
                $front = $parsed['front_matter'];

                $dateString = normalize_date_value($front['published_date']) ?? '';
                $dt = parse_post_datetime_with_timezone($dateString, $config);
                $timestamp = $dt ? $dt->getTimestamp() : 0;

                $knownFrontKeys = ['title', 'slug', 'date', 'status', 'tags', 'description', 'categories'];
                $extraFront = array_diff_key($front, array_flip($knownFrontKeys));
                
                $bear_publish = $extraFront['publish'] ?? 'true';
                $bear_page    = $extraFront['is_page'] ?? 'false';
                $extraFront['import_note']  = '';                
                $extraFront['import'] = true;
                if ($bear_page    != 'false') { $extraFront['import_note'] = 'Is a Page. Will NOT import. '; }
                if ($bear_publish != 'true')  { $extraFront['import_note'] = 'Will be saved as Draft Post.';}
                if (preg_match('/!\[.*?\]/', $parsed['content'])) { $extraFront['import_note'] = 'Check Image location. Will save as Draft';}

                $status = $bear_publish =='true' ? 'published' : 'draft';
                $posts[] = array_merge($extraFront, [
                    'title' => $front['title'] ?? 'Untitled',
                    'slug' => $front['slug'] ?? '',
                    'date' => $dateString,
                    'timestamp' => $timestamp,
                    'status' => $status,
                    'tags' => $front['tags'] ?? [],
                    'description' => $front['meta_description'] ?? '',
                    'content' => $parsed['content'],
                    'path' => $file,
                ]);
            }

            usort($posts, fn($a, $b) => ($b['date'] <=> $a['date']));
            $cache = $posts;
        }
    }
    return $cache;
}

function save_all_import_posts(array $posts, string $force, string $delete_file): string 
{
    $html='';
    foreach ($posts as $post) {
        if (preg_match('/!\[.*?\]/', $post['content'])):
            if ($force != 'y'):
                $post['status'] = 'draft';
            else:
                $post['import_note'] = '';
            endif;
        endif;
        $bear_page = $post['is_page'] ?? 'false';
        if ($bear_page == 'false'):
            $result = save_post($post);
        endif;
        if ($post['import_note'] != ''):
            $html.='<li>' . $post['import_note'] .' : '.$post['title'].'</li>'; 
        endif;
        if ($delete_file == 'y'):
            unlink($post['path']);
        endif;
    }
    return $html;
}

$pageTitle = 'Import from BearBlog';
$metaDescription = '';

require __DIR__ . '/includes/header.php';
render_masthead_layout($config, ['page' => $page ?? null]);


?>
    <main>
        <h1>BearBlog Import <?= ($import_action=='go' ? 'Completed' :'');?></h1>
        <?php if ($not_import!=''): ?>
        <p>Posts with notes:</p>
        <ul><?= $not_import; ?></ul>
        <?php endif; ?>
        
        <?php if ($to_import!=''): ?>
        <p>The following will be published:</p>
        <ul><?= $to_import; ?></ul>
        <hr>
        <form>
            <p><input type="checkbox" name="force" value="y"> Publish Posts with images?</p>
            <p><input type="checkbox" name="del"   value="y"> Delete files after import?</p>
            <input type="hidden" name="action" value="go">
            <p><button onclick="this.hidden=true; document.getElementById('imp_wait').style.display = 'block';">Import</button><span id="imp_wait" style="display:none;">Importing....</span></p>
        </form>
        <?php endif;?>
        <?=$error_msg;?>

    </main>
<?php render_footer_layout($config, ['page' => $page ?? null]); ?>
</body>
</html>
