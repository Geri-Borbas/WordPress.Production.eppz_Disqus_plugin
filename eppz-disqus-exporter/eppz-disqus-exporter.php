<?php

/*
Plugin Name: eppz! Disqus exporter
Description: Simple plugin that post process a WordPress export file to include avatars for Disqus import. Works only if SSO is enabled in your Disqus account.
Author: Gergely Borbás
Version: 0.1
*/


// Instance.
$eppz_disqus_plugin = new EPPZ_Disqus_Plugin();

// Plugin.
class EPPZ_Disqus_Plugin
{


    public function __construct()
    {
        add_action('admin_menu', array($this, 'admin_menu'));
    }

    public function admin_menu()
    {
        add_menu_page(
            'eppz! Disqus exporter',
            'Disqus exporter',
            'manage_options', // Capabilities
            'eppz-disqus-exporter-plugin', // Slug
            array($this, 'init') // Admin
        );
    }

    public function init()
    {
        ?>

        <h1>eppz! Disqus exporter</h1>

        <p style="width: 400px;">
            Meet <strong>eppz! Disqus exporter</strong>, a simple plugin that post process a
            WordPress export file to include avatars for Disqus import. Works only if SSO is
            enabled in your Disqus account.
        </p>

        <p style="width: 400px;">
            It grabs avatar urls (if any) from Gravatar each time it arrives to a comment
            author email in the WXR file, then adds an entry to the WXR that <strong>creates
            Disqus SSO user</strong> upon importing the resultin file in Disqus admin.
        </p>

        <form  method="post" enctype="multipart/form-data">
            <p>
                <label>Pick a WordPress export (WXR) file: </label>
                <input type="file" id="wordpress_export_file" name="wordpress_export_file"></input>
                <?php $this->handle_post(); ?>
                <?php submit_button('Upload and process') ?>
            </p>
        </form>

        <?php
    }

    function handle_post()
    {
        define('LINEBREAK', '<br />');
        define('WORDPRESS_NAMESPACE', 'http://wordpress.org/export/1.2/');
        define('DISQUS_NAMESPACE', 'http://disqus.com/disqus-internals');

        // Show errors.
        @ini_set('log_errors', 1);
        @ini_set('display_errors', 1);
        @ini_set('error_reporting', E_ALL ^ E_NOTICE);

        // First check if the file appears on the _FILES array
        if (isset($_FILES['wordpress_export_file']))
        {
            $file = $_FILES['wordpress_export_file'];

            // Get root element.
            $rss = simplexml_load_file($file['tmp_name']);

            // Add Disqus 'dsq' namespace.
            $rss->addAttribute('xmlns:xmlns:dsq', DISQUS_NAMESPACE);

            // Enumerate posts.
            foreach($rss->channel->item as $eachItem)
            {
                // Enumerate comments.
                foreach($eachItem->children(WORDPRESS_NAMESPACE) as $eachComment)
                {
                    if ($eachComment->getName() != 'comment') continue; // Only `<wp:comment>`
                    $this->inject_avatar_into_comment($eachComment);
                }
            }

            // Save.
            $name = 'processed.'.$file['name'];
            $path = dirname(__FILE__).'/'.$name;
            file_put_contents($path, $rss->saveXML());
            $link = plugins_url($name, __FILE__);

            ?>
            <p>
                <strong>Bang!</strong> Please find the processed WXR at <strong><a href="<?php echo $link; ?>" download><?php echo $name; ?></a></strong>.
            </p>
            <?php
        }

        // Hide errors.
        @ini_set('log_errors', 0);
        @ini_set('display_errors', 0);
        @ini_set('error_reporting', E_ALL ^ E_NOTICE);
    }

    function inject_avatar_into_comment($comment)
    {
        if ($comment->comment_author_email == null||
            $comment->comment_author_email == '') return; // Only with email

        // Gravatar email hash.
        $emailHash = md5(strtolower(trim($comment->comment_author_email)));

        if ($this->validate_gravatar($emailHash) == false) return; // Only with gravatar

        // Add Disqus SSO details tag.
        $remote = $comment->addChild('dsq:remote', '', DISQUS_NAMESPACE);

        // Unique user ID (the id of the implied Disqus SSO user).
        $emailHash = md5(strtolower(trim($comment->comment_author_email)));
        $userID = 'eppz_blog_imported_user_'.$emailHash;
        $avatar = 'http://www.gravatar.com/avatar/'.$emailHash;
        $remote->addChild('dsq:id', $userID, DISQUS_NAMESPACE);
        $remote->addChild('dsq:avatar', htmlspecialchars($avatar), DISQUS_NAMESPACE);
    }

    function validate_gravatar($emailHash)
    {
        $uri = 'http://www.gravatar.com/avatar/'.$emailHash.'?d=404';
        $headers = @get_headers($uri);
        if (!preg_match("|200|", $headers[0]))
        { $has_valid_avatar = false; } else { $has_valid_avatar = true; }
        return $has_valid_avatar;
    }
}


?>