<?php

/* helper functions for journo profile data */


require_once '../conf/general';
//require_once 'misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/person.php';
require_once '../phplib/page.php';
require_once '../../phplib/utility.php';


// base class for profile-editing pages
class EditProfilePage
{

    public $pageName = '';
    public $pagePath = '';  // eg "/profile_hobbies"
    public $P = null;   // currently logged-on user
    public $journo = null;
    public $pageTitle = '';
    public $pageParams = array();

    function __construct()
    {
//        $this->P = person_if_signed_on();
        $ref = get_http_var( 'ref' );
        $this->journo = db_getRow( "SELECT * FROM journo WHERE status='a' AND ref=?", $ref );
        $r = array(
            'reason_web' => "Edit Journalisted profile for {$this->journo['prettyname']}",
            'reason_email' => "Edit Journalisted profile for {$this->journo['prettyname']}",
            'reason_email_subject' => "Edit {$this->journo['prettyname']} on Journalisted"
        );
        // rediect to login screen if not logged in (so this may never return)
        $this->P = person_signon($r);
    }


    function run()
    {
        if( get_http_var('ajax') ) {
            $this->ajax();
        } else {
            $this->display();
        }
    }


    /* to be overriden */
    function ajax() {
    }


    /* normal, non-ajax display */
    function display()
    {
        page_header( $this->pageTitle, $this->pageParams );

        // check we're logged in
        if( is_null($this->P) ) {
            print( "<p>Sorry, you need to be logged in to edit your profile.</p>\n" );
            page_footer();
            return;
        }

        // make sure we've specified a journo
        if( is_null( $this->journo ) ) {
            print( "<p>No journalist specified.</p>\n" );
            page_footer();
            return;
        }

        // is this person allowed to edit this journo?
        if( !db_getOne( "SELECT id FROM person_permission WHERE person_id=? AND journo_id=? AND permission='edit'",
            $this->P->id(), $this->journo['id'] ) ) {
            print( "<p>Not allowed.</p>\n" );
            page_footer();
            return;
        }


        $this->showNavigation();



        $this->displayMain();
        page_footer();
    }


    function showNavigation()
    {
        $tabs = array(
            'employment'=>array( 'title'=>'Employment', 'url'=>'/profile_employment' ),
            'education'=>array( 'title'=>'Education', 'url'=>'/profile_education' ),
            'awards'=>array( 'title'=>'Awards', 'url'=>'/profile_awards' ),
            'books'=>array( 'title'=>'Books', 'url'=>'/profile_books' ),
//            'weblinks'=>array( 'title'=>'On the web', 'url'=>'/profile_weblinks' ), 
            'picture'=>array( 'title'=>'Picture', 'url'=>'/profile_picture' ),
        );

        // show the main nav bar

        if( array_key_exists( $this->pageName, $tabs ) ) {
            $default_url = $tabs[$this->pageName]['url'];
        } else {
            $default_url = $tabs['employment']['url'];
        }

?>
<h2>Welcome, <a href="/<?=$this->journo['ref'];?>"><em><?=$this->journo['prettyname'];?></em></a></h2>

<div class="pipeline">
 <span class="<?=$this->pageName=='admired'?'active':'';?>">1. <a href="/profile_admired?ref=<?=$this->journo['ref'];?>">Journalists you admire</a></span>
 <span class="<?=array_key_exists($this->pageName,$tabs)?'active':'';?>">2. <a href="<?=$default_url;?>?ref=<?=$this->journo['ref'];?>">Add to your profile</a></span>
 <span class="<?=$this->pageName=='missing'?'active':'';?>">3. <a href="/profile_missing?ref=<?=$this->journo['ref'];?>">Tell us anything we've missed/got wrong</a></span>
</div>
<?php

        // secondary tab bar

        if( $this->pageName != 'admired' && $this->pageName != 'missing' ) {
            // show the tabs
/*
            $tabs = array(
                'employment'=>array( 'title'=>'Employment', 'url'=>'/profile_employment' ),
                'education'=>array( 'title'=>'Education', 'url'=>'/profile_education' ),
                'awards'=>array( 'title'=>'Awards', 'url'=>'/profile_awards' ),
                'books'=>array( 'title'=>'Books', 'url'=>'/profile_books' ),
            );
*/

?>
<ul class="tabs">
<?php foreach( $tabs as $tabname=>$tab ) {
 ?>
<?php  if($tabname==$this->pageName) { ?>
<li class="current"><a href="<?= "{$tab['url']}?ref={$this->journo['ref']}"; ?>"><?= $tab['title']; ?></a></li>
<?php  } else{ ?>
<li><a href="<?= "{$tab['url']}?ref={$this->journo['ref']}"; ?>"><?= $tab['title']; ?></a></li>
<?php  } ?>
<?php } ?>
</ul>

<?php

        }
    }

    // derived pages override this.
    function displayMain()
    {
    }




    /* helper for submitting items */
    function genericFetchItemFromHTTPVars( $fieldnames ) {
        $item = array();
        foreach( $fieldnames as $f )
            $item[$f] = get_http_var($f);
        $item['id'] = get_http_var('id');
        return $item;
    }


    /* return a "remove" link for the given item */
    function genRemoveLink( $entry_id ) {
        return <<<EOT
<a class="remove" href="{$this->pagePath}?ref={$this->journo['ref']}&remove_id={$entry_id}">remove</a>
EOT;
    }


    /* helper for submitting items */
    /* for new items, adds the id field upon insert */
    function genericStoreItem( $tablename, $fieldnames, &$item )
    {

        if( $item['id'] ) {
            /* update existing entry */
            
            $frags = array();
            $params = array();
            foreach( $fieldnames as $f ) {
                $frags[] = "$f=?";
                $params[] = $item[$f];
            }

            /* note, restrict by journo id to stop people hijacking others entries! */
            $sql = "UPDATE {$tablename} SET " . implode( ',', $frags ) . " WHERE id=? AND journo_id=?";
            $params[] = $item['id'];
            $params[] = $this->journo['id'];

            db_do( $sql, $params );
        } else {
            /* insert new entry */

            $frags = array( '?' );
            $params = array( $this->journo['id'] );
            foreach( $fieldnames as $f ) {
                $frags[] = "?";
                $params[] = $item[$f];
            }
            $sql = "INSERT INTO {$tablename} (journo_id," . implode( ",", $fieldnames) . ") ".
                "VALUES (" . implode(',',$frags) . ")";
            db_do( $sql, $params );
            $item['id'] = db_getOne( "SELECT lastval()" );
        }
        db_commit();

        return $item['id'];
    }

}


?>
