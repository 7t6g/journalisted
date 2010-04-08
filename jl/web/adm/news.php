<?php

// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../phplib/markdown.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../phplib/adm.php';

$action = get_http_var( 'action' );


function extra_head()
{
?>
    <script type="text/javascript" src="/js/jquery-fieldselection.js"></script>
    <script language="javascript" type="text/javascript">
       $(document).ready(
       function () {

         function markdownSearch( query, daterange ) {
           var q = $.trim(query);
                    
           if( q.search(' ')!=-1 ) {
              q = '"' + q + '"';
           }
           if( daterange ) {
               q = q + " " + daterange;
           }
           var url = "/search?a=" + escape(q);
           var out = "[" + query + "](" + url + ")";
            return out;
          }

          $("#linkify-journo").click( function(e) {
              var journo = $("#content").getSelection().text;
              if( journo ) {
                  var ref = journo.toLowerCase();
                  ref = ref.replace(/\s+/g, "-")
                  var out = "[" + journo + "](/" + ref + ")";
                  $('#content').replaceSelection( out,true );
              }
   	          e.preventDefault();
           } );

           $("#linkify-search").click( function(e) {
             var txt = $("#content").getSelection().text;
             if( txt ) {
               txt = markdownSearch( txt );
               $("#content").replaceSelection( txt, true );
             }
   		     e.preventDefault();
           } );

    
           $("#linkify-searchdaterange").click( function(e) {
             var from = $("#date_from").val();
             var to = $("#date_to").val();
             if( from=="" || to=="" ) {
                 alert("Bad range - fill it out!");
   		         e.preventDefault();
                 return;
             }
             var txt = $("#content").getSelection().text;
             if( txt ) {
                 txt = markdownSearch( txt, from+".."+to );
                 $('#content').replaceSelection( txt,true );
             }
   		     e.preventDefault();
           } );
       } );
    </script>
<?php

}




// handy default
if( $action == '' && get_http_var( 'id' ) )
    $action = 'edit';

admPageHeader( "Edit news", "extra_head" );

switch( $action ) {
    case 'create':
        ?><h2>Post New</h2><?php
        $post = newsBlankPost();
        newsEdit( $post );
        break;
    case 'edit':
        $id = get_http_var( 'id' );
        $post = db_getRow( "SELECT * FROM news WHERE id=?", $id );
        newsEdit( $post );
        break;
    case 'Preview':
        $post = newsFromHTTPVars();
        newsPreview( $post );
        newsEdit( $post );
        break;
    case 'Save':
        $post = newsFromHTTPVars();
        newsSave( $post );
        newsList();
        break;
    case 'delete':
        $id = get_http_var( 'id' );
        $post = db_getRow( "SELECT * FROM news WHERE id=?", $id );
?>
<p>
<strong>Do You really want to kill this post?</strong><br/>
</p>
<p>
<a href="/adm/news" />No, I've changed my mind</a>
&nbsp;&nbsp; 
<small><a href="/adm/news?action=reallydelete&id=<?=$id?>" />Yes, delete it!</a></small>
</p>
<?php
        newsPreview( $post );
        break;
    case 'reallydelete':
        $id = get_http_var( 'id' );
        $post = db_getRow( "SELECT * FROM news WHERE id=?", $id );
        newsDelete( $post );
        newsList();
        break;
    default:
        newsList();
        break;
}

admPageFooter();

function newsList() {

    $posts = db_getAll( "SELECT id,status,title,slug,posted,author FROM news ORDER BY posted DESC" );

?>
<h2>News Posts</h2>
 <a href="/adm/news?action=create">Create a new post</a>
 <ul>
<?php foreach( $posts as $p ) { ?>
  <li class="<?= ($p['status']=='a') ? 'approved':'unapproved' ?>" >
    <?php if( $p['status'] == 'a' ) { ?>
    <strong><a href="/news/<?= $p['slug']; ?>"><?= $p['title'] ?></a></strong>
    <?php } else { ?>
    <strong><?= $p['title']?></strong> (unpublished)
    <?php } ?>
    <small>posted by <em><?=$p['author'] ?></em>, <?= $p['posted'] ?>.</small>
    <br/>
      <a href="/adm/news?action=edit&id=<?= $p['id'] ?>">[edit]</a>
      <small> <a href="/adm/news?action=delete&id=<?= $p['id'] ?>">[delete]</a> </small>
    <br/>
  </li>
<?php } ?>
 </ul>

<?php




}
        

function newsFromHTTPVars() {
    $post = array(
        'title'=>get_http_var('title'),
        'status'=>get_http_var('status','u'),
        'author'=>get_http_var('author'),
        'slug'=>get_http_var('slug'),
        'content'=>get_http_var('content'),
        'kind'=>get_http_var('kind'),
        'date_from'=>get_http_var('date_from'),
        'date_to'=>get_http_var('date_to'),
    );

    if( !$post['date_from'] )
        $post['date_from'] = null;
    if( !$post['date_to'] )
        $post['date_to'] = null;

    if( $post['slug'] == '' ) {
        $slug = strtolower( $post['title'] );
        $slug = preg_replace("/[^a-zA-Z0-9 ]/", "", $slug );
        $slug = str_replace(" ", "-", $slug);
        $post['slug'] = $slug;
    }

    $id = get_http_var('id' );
    if( $id )
        $post['id'] = $id;
    return $post;
}


function newsBlankPost() {
    return array(
        'status'=>'u',  // unpublished
        'title'=>'',
        'author'=>'',
        'slug'=>'',
        'content'=>'',
        'kind'=>'',
        'date_from'=>null,
        'date_to'=>null );
}


function newsEdit($post) {

    $news_kinds = array( 'newsletter' => 'Newsletter', ''=>'Generic News' );

?>
<form method="POST" action="/adm/news">
<label for="status">Published?</label>
<input type="checkbox" name="status" value='a' <?= $post['status']=='a'?'checked':'' ?> />
<br />

<label for="kind">Kind:</label>
<select name="kind" id="kind">
<?php foreach( $news_kinds as $k=>$kdesc ) { ?>
 <option <?= $post['kind']==$k ? 'selected ':'' ?>value="<?= $k ?>"><?= $kdesc ?></option>
<?php } ?>
</select><br />
<label for="title">Title:</label>
<input type="text" size="80" name="title" id="title" value="<?= $post['title'] ?>" /><br />
<label for="author">Author:</label>
<input type="text" size="80" name="author" id="author" value="<?= $post['author'] ?>" /><br />
<label for="slug">Slug:</label>
<input type="text" size="80" name="slug" id="slug" value="<?= $post['slug'] ?>" /><br />
<label for="date_from">from (yyyy-mm-dd):</label>
<input title="yyyy-mm-dd" type="text" id="date_from" name="date_from" value="<?= $post['date_from'] ?>" /><br />
<label for="date_to">to (yyyy-mm-dd):</label>
<input title="yyyy-mm-dd" type="text" id="date_to" name="date_to" value="<?= $post['date_to'] ?>" /><br />

<div class="news-toolbar">
 <a title="Convert selected text to a journo link" id="linkify-journo" href="#">journo</a>
 <a title="Convert selected text to a search link" id="linkify-search" href="#">search</a>
 <a title="Convert selected text to a search link with date range" id="linkify-searchdaterange" href="#">search within daterange</a>
</div>
<textarea rows="20" cols="100" name="content" id="content">
<?= $post['content'] ?>
</textarea>
<br />

<?php if( array_key_exists( 'id', $post ) ) { ?>
<input type="hidden" name="id" value="<?= $post['id'] ?>" />
<?php } ?>
<input type="submit" name="action" value="Preview" />
<input type="submit" name="action" value="Save" />
</form>
<?php

}


// saves post to database. if it's a new post, its new id will be added to $post
function newsSave( &$post )
{

    if( array_key_exists( 'id', $post ) ) {
        // update existing post
        db_do( "UPDATE news SET status=?, title=?, author=?, slug=?, content=?, kind=?, date_from=?, date_to=? WHERE id=?",
            $post['status'],$post['title'],$post['author'],$post['slug'],$post['content'], $post['kind'], $post['date_from'], $post['date_to'], $post['id'] );

    } else {
        db_do( "INSERT INTO news (status, title, author, posted, slug, content,kind,date_from,date_to) VALUES (?,?,?,NOW(),?,?,?,?,?)",
            $post['status'],$post['title'],$post['author'],$post['slug'],$post['content'], $post['kind'], $post['date_from'], $post['date_to'] );
        $post['id'] = db_getOne( "SELECT lastval()" );
    }
    db_commit();
?>
<div class="action_summary">
Saved <a href="/news/<?= $post['slug']?>"><?= $post['title'] ?></a>
</div>
<?php
}




function newsPreview( $post )
{

    $html = Markdown( $post['content'] );
?>
<p>preview:</p>
<div class="news-preview" style="border: 1px solid black; padding: 1em; margin: 2em;">
<?= $html ?>
</div>
<?php
}

function newsDelete( $post )
{
    db_do( "DELETE FROM news WHERE id=?", $post['id'] );
    db_commit();
?>
<div class="action_summary">Deleted '<?= $post['title']; ?>'</div>
<?php
}

?>

