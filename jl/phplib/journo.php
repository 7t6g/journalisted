<?php
/* Common journo-related functions */


require_once '../conf/general';
require_once 'misc.php';
require_once '../../phplib/db.php';
require_once '../phplib/gatso.php';

define( 'OPTION_JL_NUM_SITES_COVERED', '14' );

function journo_emitPageQuick( &$journo )
{

    printf("<!-- generated by journo_emitPageQuick() %s -->\n", date('Y-m-d H:i' ) );
    journo_emitPage( $journo );
}

function journo_emitPageFull( &$journo )
{
    printf("<!-- generated by journo_emitPageFull() %s -->\n", date('Y-m-d H:i' ) );
    $slowdata = journo_calculateSlowData( $journo );

    journo_emitPage( $journo, $slowdata );
}

function journo_emitPage( &$journo, &$slowdata = array() )
{
//  printf( "<h2>%s</h2>\n", $journo['prettyname'] );

    /* main pane */

gatso_start("maincolumn");
    print "<div id=\"maincolumn\">\n";

    gatso_start('overview');
    journo_emitOverviewBlock( $journo, $slowdata );
    gatso_stop('overview');
    gatso_start('article_list');
    journo_emitArticleblocks( $journo, $slowdata );
    gatso_stop('article_list');
    journo_emitFriendlyStatsBlock( $journo, $slowdata );
    journo_emitByNumbersBlock( $journo, $slowdata );

?>
    <div class="caution">
        Caution: this list is not comprehensive but based on articles published in
        <a href="/faq/what-news-outlets-does-journalisted-cover">21 UK news outlets across <?php echo OPTION_JL_NUM_SITES_COVERED; ?> different websites</a>.
        The information is collected automatically so there are bound to be mistakes.
        Please 
        <a href="/forjournos?j=<?=$journo['ref'];?>">let us know</a>
        when you find one so we can correct it.
    </div>
<?

    print "</div> <!-- end maincolumn -->\n";
gatso_stop("maincolumn");


    /* small column */

gatso_start("smallcolumn");
?>
<div id="smallcolumn">

<?php donatebutton_emit(); ?>

<div class="action-box">
 <div class="action-box_top"><div></div></div>
  <div class="action-box_content">

        <a href="/alert?Add=1&amp;j=<?php echo $journo['ref']; ?>">Email me</a>
        when <?php echo $journo['prettyname']; ?> writes an article

  </div>
 <div class="action-box_bottom"><div></div></div>
</div>

<?php
    journo_emitLinksBlock( $journo );
    journo_emitTagsBlock( $journo, $slowdata );
    journo_emitSimilarJournosBlock( $journo );
    journo_emitOtherArticlesBlock($journo);
    journo_emitSearchboxBlock( $journo );
?>

<div class="action-box">
 <div class="action-box_top"><div></div></div>
  <div class="action-box_content">

 <h3>Something wrong/missing?</h3>
  <p>Have we got the wrong information about this journalist?
   <a href="/forjournos?j=<?=$journo['ref'];?>">Let us know</a></p>

  </div>
 <div class="action-box_bottom"><div></div></div>
</div>

</div> <!-- end smallcolumn -->

<?php
gatso_stop("smallcolumn");
}



// TODO: sort out!
function journo_emitBasedDisclaimer()
{
/*
    $basedDisclaimer = '';
if( array_key_exists('num_articles',$slowdata) &&
    array_key_exists('first_pubdate',$slowdata) ) {

    $basedDisclaimer = sprintf( "<p class=\"disclaimer\">Based on %d article%s published in %s since %s.</p>\n",
        $slowdata['num_articles'], 
        $slowdata['num_articles']==1 ? "" : "s", // plural
        "<a href=\"/faq/what-news-outlets-does-journalisted-cover\">"OPTION_JL_NUM_SITES_COVEREDnews websites</a>,
        $slowdata['first_pubdate'] );
}
*/
}

// list recent articles for this journo
function journo_emitArticleBlocks( &$journo, &$slowdata )
{
    $allarticles = "no";

    $num_articles = null;
    if( array_key_exists('num_articles', $slowdata ) ) {
        $num_articles = $slowdata['num_articles'];
    }

    $journo_id = $journo['id'];

    $sql = <<<EOT
SELECT a.id,a.title,a.description,a.pubdate,a.permalink, o.prettyname as srcorgname, a.srcorg,a.total_bloglinks,a.total_comments
    FROM article a
        INNER JOIN journo_attr attr ON a.id=attr.article_id
        INNER JOIN organisation o ON o.id=a.srcorg
    WHERE a.status='a' AND attr.journo_id=?
    ORDER BY a.pubdate DESC
EOT;

    $sqlparams = array( $journo_id );

    $maxprev = 10;  /* max number of previous articles to list by default*/
    if( $allarticles != 'yes' ) {
        $sql .= ' LIMIT ?';
        /* note: we try to fetch 2 extra articles - one for 'most recent', the
        other so we know to display the "show all articles" prompt. */
        $sqlparams[] = 1 + $maxprev + 1;
    }

    $arts = db_getAll( $sql, $sqlparams );


    /* will we need to show a "show all articles" prompt? */
    $show_more_prompt = FALSE;
    if( $allarticles != 'yes' && sizeof($arts)>(1+$maxprev) ) {
        $show_more_prompt = TRUE;
        /* throw away all the surplus articles */
        while( sizeof($arts) > (1+$maxprev) ) {
            array_pop( $arts );
        }
    }

    /* augment results with pretty formatted date and buzz info */
    foreach( $arts as &$a ) {
        $d = new datetime( $a['pubdate'] );
        $a['pretty_pubdate'] = pretty_date(strtotime($a['pubdate']));
        $a['iso_pubdate'] = $d->format('c');
        $a['buzz'] = BuzzFragment( $a );
    }
    /* sigh... php trainwreck. without this unset, the last element in array gets blatted-over
      if we again use $a in a foreach loop. Which we do.
      Because $a is still referencing last element. Gah.
      see  http://bugs.php.net/bug.php?id=29992 for gory details. */
    unset($a);

    $newest_art = array_shift( $arts );

    if( $newest_art ) {
        /* add an image to the newest article, if there is one */

        $img = null;
// Images disabled until we've got a better handle on legal implications.
//        $img = db_getRow( "SELECT * FROM article_image WHERE article_id=? LIMIT 1", $newest_art['id'] );
        if( $img )
        {
            $img['thumb_w'] = 128;
            $img['thumb_url'] = sprintf( '/imgsize?img=%s&w=%d&unsharp=1',
                urlencode( $img['url'] ),
                $img['thumb_w'] );
        }
        $newest_art['image'] = $img;
    }

?>
<div class="box">
  <h3>Most Recent article</h3>
  <div class="box-content art-list">
<!--<div class="box recent">-->
    <div class="hentry">
      <?php $img=$newest_art['image']; if( $img ) { ?>
      <img class="thumb" src="<?php echo $img['thumb_url']; ?>" />
      <?php } ?>
      <h4 class="entry-title"><a href="<?php echo article_url($newest_art['id']);?>"><?php echo $newest_art['title']; ?></a></h4>
      <span class="publication"><?php echo $newest_art['srcorgname']; ?>,</span>
      <abbr class="published" title="<?php echo $newest_art['iso_pubdate']; ?>"><?php echo $newest_art['pretty_pubdate']; ?></abbr>
      <?php if( $newest_art['buzz'] ) { ?> (<?php echo $newest_art['buzz']; ?>)<br /> <?php } ?><br/>
      <blockquote class="entry-summary">
        <?php echo $newest_art['description']; ?>
      </blockquote>

      <div style="clear:both;"></div>

      <div class="art-info">
        <a class="extlink" href="<?php echo $newest_art['permalink'];?>" >Original article at <?php echo $newest_art['srcorgname']; ?></a><br/>
      </div>
    </div>
  </div>
</div>

<div class="box">
  <h3>Previous Articles</h3>
  <div class="box-content">
  <ul class="art-list">

<?php unset($a); foreach( $arts as $a ) { ?>
    <li class="hentry">
        <h4 class="entry-title"><a href="<?php echo article_url($a['id']);?>"><?php echo $a['title']; ?></a></h4>
        <span class="publication"><?php echo $a['srcorgname']; ?>,</span>
        <abbr class="published" title="<?php echo $a['iso_pubdate']; ?>"><?php echo $a['pretty_pubdate']; ?></abbr>
        <?php if( $a['buzz'] ) { ?> (<?php echo $a['buzz']; ?>)<?php } ?><br/>
        <div class="art-info">
          <a class="extlink" href="<?php echo $a['permalink'];?>" >Original article at <?php echo $a['srcorgname']?></a><br/>
        </div>
    </li>
<?php } ?>

  </ul>
  <p class="disclaimer">Published on one or more of <a href="/faq/what-news-outlets-does-journalisted-cover"><?php echo OPTION_JL_NUM_SITES_COVERED; ?> websites</a>.</p>

<?php if($show_more_prompt) { ?>
<?php   if( !is_null($num_articles)) { ?>
  (<a href="/<?echo $journo['ref'];?>?allarticles=yes">Show all <?php echo $num_articles; ?> articles</a>)
<?php   } else { ?>
  (<a href="/<?echo $journo['ref'];?>?allarticles=yes">Show all articles</a>)
<?php   } ?>
<?php } ?>

<p>Article(s) missing? If you notice an article is missing,
<a href="/missing?j=<?php echo $journo['ref'];?>">click here</a></p>
</div>
</div>

<?php

}


// some friendly (sentence-based) stats
function journo_emitFriendlyStatsBlock( &$journo, &$slowdata )
{
    $journo_id = $journo['id'];
    if( !array_key_exists( 'toptag_alltime', $slowdata ) &&
        !array_key_exists( 'toptag_month', $slowdata ) )
    {
        return;
    }

?>
<div class="box friendlystats">
<h3><?php echo $journo['prettyname']; ?> has written...</h3>
<div class="box-content">
<ul>
<?php
    
    // more about <X> than anything else
    if( array_key_exists( 'toptag_alltime', $slowdata ) )
    {
        $link = tag_gen_link( $slowdata['toptag_alltime'], $journo['ref'] );
        printf( "<li>More about '<a href =\"%s\">%s</a>' than anything else</li>", $link, $slowdata['toptag_alltime'] );
    }
    // a lot about <Y> in the last month
    if( array_key_exists( 'toptag_month', $slowdata ) )
    {
        $link = tag_gen_link( $slowdata['toptag_month'], $journo['ref'] );
        printf( "<li>A lot about '<a href =\"%s\">%s</a>' in the last month</li>", $link, $slowdata['toptag_month'] );
    }
?>
</ul>
<?php journo_emitBasedDisclaimer(); ?>
</div>
</div>
<?php

}





function journo_emitByNumbersBlock( &$journo, &$slowdata )
{
    $journo_id = $journo['id'];


?>
<div class="box bynumbers">
  <h3><?php echo $journo['prettyname']; ?> by numbers...</h3>
  <div class="box-content">

<?php if( array_key_exists('wc_avg',$slowdata) &&
    array_key_exists('wc_min',$slowdata) &&
    array_key_exists('wc_max',$slowdata) ) {
?>
    <ul>
      <li><?php echo $slowdata['num_articles'];?> articles (since <?php echo $slowdata['first_pubdate'];?>)</li>
      <li>Average article: <?php printf( "%.0f", $slowdata['wc_avg']/30); ?> column inches (<?php printf( "%.0f", $slowdata['wc_avg']); ?> words)</li>
      <li>Shortest article: <?php printf( "%.0f", $slowdata['wc_min']/30); ?> column inches (<?php printf( "%.0f", $slowdata['wc_min']); ?> words)</li>
      <li>Longest article: <?php printf( "%.0f", $slowdata['wc_max']/30); ?> column inches (<?php printf( "%.0f", $slowdata['wc_max']); ?> words)</li>
    </ul>
    <small>(<a href="/faq/what-are-column-inches">what are column inches?</a>)</small>
<?php } else { ?>
    <p>(sorry, information not currently available)</p>
<?php } ?>
  </div>
</div>


<?php

}



function journo_emitTagsBlock( &$journo, &$slowdata )
{
    $journo_id = $journo['id'];
    $ref = $journo['ref'];
    $prettyname = $journo['prettyname'];

    $tags = null;
    if( array_key_exists('tags', $slowdata ) )
        $tags = $slowdata['tags'];

?>
<div class="box">
<h3>The topics <?=$prettyname; ?> mentions most:</h3>
<div class="box-content">
<div class="tags">
<?php
    if( !is_null( $tags ) ) {
        tag_cloud_from_getall( $tags, $ref );
    } else {
?>
    <p>(sorry, information not currently available)</p>
<?php
    }
?>

</div>
</div>
</div>

<?php

}

// not sure if this is cheesy or not... should bio links also be added into the weblinks table?
function journo_getBioLinks( &$journo )
{
    $links = array();

    $rows = db_getAll( "SELECT srcurl,type FROM journo_bio WHERE approved=true AND journo_id=?", $journo['id'] );

    foreach( $rows as $r )
    {
        $desc = '';
        if( $r['type'] == 'cif:contributors-az' ) {
            $desc = "Biography (from The Guardian)";
        } elseif( $r['type'] == 'wikipedia:journo' ) {
            $desc = "Biography (from Wikipedia)";
        } else {
            continue;
        }
        $links[] = array( 'url'=>$r['srcurl'], 'description'=>$desc );
    }

    return $links;
}



// list any links to other places on the web for this journo
function journo_emitLinksBlock( &$journo )
{
    $journo_id = $journo['id'];
    $sql = "SELECT url, description " .
        "FROM journo_weblink " .
        "WHERE journo_id=? " .
        "AND journo_weblink.type!='cif:blog:feed' " .
        "AND approved";

    $links = db_getAll( $sql, $journo_id );
    $links = array_merge( $links, journo_getBioLinks( $journo ) );
?>
<div class="box links">
  <h3>More useful links for <?php echo $journo['prettyname']; ?></h3>
  <div class="box-content">
    <ul>
<?php foreach( $links as $l ) { ?>
       <li><a class="extlink" href="<?php echo $l['url']; ?>"><?php echo $l['description']; ?></a></li>
<?php } ?>
    </ul>
    <div class="box-action"><a href="/forjournos?j=<?php echo $journo['ref'];?>">Suggest a link for <?php echo $journo['prettyname']; ?></a></div>
    <div style="clear: both;"></div>
  </div>
</div>
<?php

}


// list other articles by this journo (ie from publications we don't cover)
function journo_emitOtherArticlesBlock( &$journo )
{
    $journo_id = $journo['id'];
    $sql = "SELECT * FROM journo_other_articles WHERE journo_id=? AND status='a' ORDER BY pubdate DESC";
    $others = db_getAll( $sql, $journo_id );

?>
<div class="box links">
  <h3>Articles by <?php echo $journo['prettyname'];?> published elsewhere</h3>
  <div class="box-content">
  <small>(<a href="/faq/do-you-cover-articles-from-other-news-outlets">what's this?</a>)</small>
    <ul class="art-list">
<?php unset( $o ); foreach( $others as $o ) { ?>
      <li>
        <h4 class="entry-title"><a class="extlink" href="<?php echo $o['url']; ?>"><?php echo $o['title']; ?></a></h4>
        <?php if($o['publication']) { ?><span class="publication">, <?php echo $o['publication'];?></span><?php } ?>
        <abbr class="published" title="<?php echo $o['pubdate']; ?>">, <?php echo pretty_date( strtotime($o['pubdate']) ); ?></abbr>
      </li>
<?php } ?>
    </ul>
    <div class="box-action"><a href="/missing?j=<?php echo $journo['ref']; ?>">Tell us about other articles written by <?php echo $journo['prettyname']; ?></a></div>
    <div style="clear: both;"></div>
  </div>
</div>
<?php

}


// search box for this journo
// ("find articles by this journo containing ....")
function journo_emitSearchboxBlock( &$journo )
{

?>


<div class="action-box">
<div class="action-box_top"><div></div></div>
  <div class="action-box_content">

    <form action="/search" method="get">
    <p>Find articles by <?php echo $journo['prettyname'];?> containing:</p>
    <input id="findarticles" type="text" name="q" value=""/>
    <input type="hidden" name="j" value="<?php echo $journo['ref'];?>"/>
    <input type="submit" value="Find" />
    </form>

  </div>
<div class="action-box_bottom"><div></div></div>
</div>

<?php

}



// show whatever general info we know about this journo
// - biodata from wikipedia/whereever
// - which organisations they've written for (that we know about)
// - contact details
function journo_emitOverviewBlock( &$journo, &$slowdata )
{
    $journo_id = $journo['id'];

    $bios = journo_fetchBios( $journo_id );


    $writtenfor = null;
    if( array_key_exists('writtenfor', $slowdata ) )
        $writtenfor = $slowdata['writtenfor'];
    $rssurl = journoRSS( $journo );

    $known_email = fetchJournoEmail( $journo );
    $guessed = null;
    if( $known_email===null ) {
        /* no known email - try and guess org and email */
        $guessed = journo_guessContactDetails( $journo, $slowdata );
    }
    else {
        /* if there is an email address, but it is blank, don't display _anything_ */
        if( $known_email['email'] == '' )
            $known_email = null;
    }



?>
<div class="box strong-box overview">

  <h2><a href="<?php echo $rssurl; ?>"><img src="/images/rss.gif" alt="RSS feed" border="0" align="right"></a><?php echo $journo['prettyname']; ?></h2>
  <div class="box-content">
    <ul>
<?php
    foreach($bios as $bio ) {
?>
    <li class="bio-para"><?php echo $bio['bio']; ?>
      <div class="disclaimer">
        (source: <a class="extlink" href="<?php echo $bio['srcurl'];?>"><?php echo $bio['srcname'];?></a>)
      </div>
      <div style="clear: both;"></div>
    </li>
<?php
    }
?>

<?php if( $writtenfor ) { ?>
    <li>
      <?php echo $journo['prettyname'];?> has written articles published in <?php echo $writtenfor; ?>.
      <?php journo_emitBasedDisclaimer(); ?>
    </li>
<?php } ?>
<?php
    if( $known_email )
    {
        /* we've got a known email address - show it! */
?>
    <li>
        Email <?php echo $journo['prettyname']; ?> at: <span class="journo-email"><?php echo SafeMailTo( $known_email['email'] ); ?></span>
<?php if( $known_email['srcurl'] ) { ?>
        <div class="email-source">(from <a class="extlink" href="<?php echo $known_email['srcurl']; ?>"><?php echo $known_email['srcurlname']; ?></a>)</div>
<?php } ?>
    </li>
<?php

    }   /* end known_email */


    if( $guessed )
    {
        /* show guessed contact details */
?>
    <li>No email address known for <?php echo $journo['prettyname'];?>.<br/>
      You could try contacting <span class="publication"><?php echo $guessed['orgname']; ?></span>
<?php
        if($guessed['orgphone'])
        {
?>(Telephone: <?php echo $guessed['orgphone']; ?>)<?php
        }
?>.
<?php
        if( $guessed['emails'] )
        {
            $safe_emails = array();
            foreach( $guessed['emails'] as $e )
                $safe_emails[] = SafeMailTo( $e );
?>
      <br/>
      Based on the standard email format for <span class="publication"><?php echo $guessed['orgname']; ?></span>, the email address <em>might</em> be <?php echo implode( ' or ', $safe_emails ); ?>.
<?php
        }
?>
    </li>
<?php
    }   /* end guessed contact details */

?>
    </ul>

  </div>
</div>
<?php

}




/* use the journo data to expand a format string describing generic
 * email format of an organisation.
 * Returns an array of guessed email addresses for the journo.
 */
function expandEmailFormat( $fmt, $journo )
{
//    if( $fmt == '' )
//        return '';

    $fmt = preg_replace( '/\{FIRST\}/', $journo['firstname'], $fmt );
    $fmt = preg_replace( '/\{LAST\}/', $journo['lastname'], $fmt );
    $fmt = preg_replace( '/\{INITIAL\}/', $journo['firstname'][0], $fmt );

    $forms = preg_split( '/\s*,\s*/', $fmt );

    return $forms;
//    $forms = preg_replace( '/(.+)/', '<a href="mailto:\1">\1</a>', $forms );

//    return implode( " or ", $forms );
}



function fetchJournoEmail( $journo )
{
    $row = db_getRow("SELECT email, srcurl FROM journo_email WHERE journo_id=? AND approved",
                     $journo['id']);
    if( !$row )
        return null;
    /* we have an email address on file - show it */
    $email = $row['email'];
    /* if we got it from a webpage (or article), say which one */
    $srcurlname = null;
    if( $row['srcurl'] )
    {
        $matches = '';
        preg_match('/(?:[a-zA-Z0-9\-\_\.]+)(?=\/)/', $row['srcurl'], $matches);
        $srcurlname = $matches[0];
    }

    return array(
        'email'=>$row['email'],
        'srcurl'=>$row['srcurl'],
        'srcurlname'=>$srcurlname );
}


/* try guessing contact details for a journo. */
function journo_guessContactDetails( &$journo, &$slowdata )
{
    if( !array_key_exists('guessed_main_org', $slowdata ) )
        return null;

    $org = $slowdata['guessed_main_org' ];
//    $org = guessMainOrg( $journo['id'] );
    if( $org === null )
        return null;

    $row = db_getRow( "SELECT prettyname,phone,email_format FROM organisation WHERE id=?", $org );

    return array(
        'orgname' => $row['prettyname'],
        'orgphone' => $row['phone'],
        'emails' => expandEmailFormat( $row['email_format'], $journo )
    );
}




/* Try and guess which organisation a journo might be employed by.
 * - which org have they written for most in the last 3 months?
 * - have they written at least 5 articles for them during that time?
 * returns srcorg, or null if we can't decide.
 */
function journo_guessMainOrg( $journo_id )
{
    /* cache results for any number of journos, although we'd
     * probably never need more than one... */
	static $cached = array();
	if( array_key_exists( $journo_id, $cached ) )
		return $cached[ $journo_id ];

    gatso_start( "guessMainOrg" );
    $sql = <<<EOT
        SELECT count(*) as artcnt, foo.srcorg
            FROM (
                SELECT a.srcorg
                    FROM article a INNER JOIN journo_attr attr
                        ON (a.status='a' AND a.id=attr.article_id)
                    WHERE attr.journo_id=? AND a.pubdate>NOW()-interval '3 months'
                    ORDER BY a.pubdate DESC
                ) AS foo
                GROUP BY foo.srcorg
                ORDER BY artcnt DESC LIMIT 1;
EOT;

    $row = db_getRow( $sql, $journo_id );

    gatso_stop( "guessMainOrg" );

    if( !$row )
        return null;

    /* require at least 5 articles before we're happy */
    if( $row['artcnt'] < 5 )
        return null;

    return (int)$row['srcorg'];
}



function journo_emitSimilarJournosBlock( $journo )
{
    gatso_start( "similar_journos" );
    $sql = <<<EOT
SELECT j.prettyname, j.ref, j.oneliner
    FROM (journo_similar s INNER JOIN journo j ON j.id=s.other_id)
    WHERE s.journo_id=?
    ORDER BY s.score DESC
    LIMIT 10
EOT;
    $similar_journos = db_getAll( $sql, $journo['id'] );

?>
<div class="box similar-journos">
 <h3>Journalists who write similar articles</h3>
 <small>(<a class="tooltip" href="/faq/how-does-journalisted-work-out-what-journalists-write-similar-stuff">what's this?</a>)</small>
 <div class="box-content">
  <ul>
<?php foreach( $similar_journos as $j ) { ?>
   <li><a href="<?php echo '/'.$j['ref']; ?>"><?php echo $j['prettyname']; ?></a> (<?php echo $j['oneliner']; ?>)</li>
<?php } ?>
  </ul>
 </div>
</div>
<?php
    gatso_stop( "similar_journos" );

}

/* return the url of the RSS feed for this journo */
function journoRSS( $journo ) {
    return sprintf( "http://%s/%s/rss", OPTION_WEB_DOMAIN, $journo['ref'] );
}



/* returns a single line of the organisations the journo has written for
 *  eg "The Daily Mail, The Guardian and The Observer"
 * includes any known jobtitles.
 */
function journo_calcWrittenFor( $journo_id )
{

    $orgs = get_org_names();

    $writtenfor = db_getAll( "SELECT DISTINCT a.srcorg " .
        "FROM article a INNER JOIN journo_attr j ON (a.status='a' AND a.id=j.article_id) ".
        "WHERE j.journo_id=?",
        $journo_id );

    $orglist = array();
    foreach( $writtenfor as $row )
    {
        $srcorg = $row['srcorg'];
        // get jobtitles seen for this org: 
        $titles = db_getAll( "SELECT jobtitle FROM journo_jobtitle WHERE journo_id=? AND org_id=?",
            $journo_id, $srcorg );
        $titlelist = array();
        foreach( $titles as $t )
            $titlelist[] = $t['jobtitle'];

        $s = "<span class=\"publication\">" . $orgs[ $srcorg ] . "</span>";
        if( !empty( $titlelist ) )
            $s .= ' (' . implode( ', ', $titlelist) . ')';
        $orglist[] = $s;
    }

    $writtenfor = pretty_implode( $orglist);

    return $writtenfor;
}

    



/* fetch any bios we've got stored for this journo.
 * returns an array of bio arrays, with these fields:
 *  'bio': the bio text
 *  'srcurl': url is came from, if any
 *  'srcname': name of place the bio came from (eg "Wikipedia")
 */
function journo_fetchBios( $journo_id )
{
    $bios = array();
    $q = db_query("SELECT bio, srcurl, type FROM journo_bio " .
                     "WHERE journo_id=? AND approved",
                     $journo_id);

    while( $row = db_fetch_array($q) )
    {
        switch( $row['type'] ) {
            case 'wikipedia:journo':
                $srcname='Wikipedia';
                break;
            case 'cif:contributors-az':
                $srcname='Comment is free';
                break;
            default:
                $srcname=$row['srcurl'];
                break;
        }

        $bios[] = array(
            'bio'=> $row['bio'],
            'srcurl' => $row['srcurl'],
            'srcname' => $srcname
        );
    }

    return $bios;
}


// get various stats for this journo
function journo_calcStats( $journo )
{
    $journo_id = $journo['id'];
    $stats = array();

    // wordcount stats, number of articles...
    $sql = "SELECT SUM(s.wordcount) AS wc_total, ".
            "AVG(s.wordcount) AS wc_avg, ".
            "MIN(s.wordcount) AS wc_min, ".
            "MAX(s.wordcount) AS wc_max, ".
            "to_char( MIN(s.pubdate), 'Month YYYY') AS first_pubdate, ".
            "COUNT(*) AS num_articles ".
        "FROM (journo_attr a INNER JOIN article s ON (s.status='a' AND a.article_id=s.id) ) ".
        "WHERE a.journo_id=?";
    $stats = db_getRow( $sql, $journo_id );

    // most frequent tag over last month
    $sql = "SELECT t.tag, sum(t.freq) as mentions ".
        "FROM ((article_tag t INNER JOIN journo_attr attr ON attr.article_id=t.article_id) ".
            "INNER JOIN article a ON a.id=t.article_id) ".
        "WHERE t.kind<>'c' AND attr.journo_id = ? AND a.status='a' AND a.pubdate>NOW()-interval '1 month' ".
        "GROUP BY t.tag ".
        "ORDER BY mentions DESC ".
        "LIMIT 1";
    $row = db_getRow( $sql, $journo_id );
    if( $row )
        $stats['toptag_month'] = $row['tag'];

    // most frequent tag  of all time
    $sql = "SELECT t.tag, sum(t.freq) as mentions ".
        "FROM ((article_tag t INNER JOIN journo_attr attr ON attr.article_id=t.article_id) ".
            "INNER JOIN article a ON a.id=t.article_id) ".
        "WHERE t.kind<>'c' AND attr.journo_id = ? AND a.status='a' ".
        "GROUP BY t.tag ".
        "ORDER BY mentions DESC ".
        "LIMIT 1";
    $row = db_getRow( $sql, $journo_id );
    if( $row )
        $stats['toptag_alltime'] = $row['tag'];

    return $stats;
}



function journo_calculateSlowData( &$journo ) {

    $slowdata = journo_calcStats( $journo );

    /* TAGS */
    $maxtags = 20;
    # TODO: should only include active articles (ie where article.status='a')
    $sql = "SELECT t.tag AS tag, SUM(t.freq) AS freq ".
        "FROM ( journo_attr a INNER JOIN article_tag t ON a.article_id=t.article_id ) ".
        "WHERE a.journo_id=? AND t.kind<>'c' ".
        "GROUP BY t.tag ".
        "ORDER BY freq DESC " .
        "LIMIT ?";
    $slowdata['tags'] = db_getAll( $sql, $journo['id'], $maxtags );
    $slowdata['guessed_main_org'] = journo_guessMainOrg( $journo['id'] );
    $slowdata['writtenfor'] = journo_calcWrittenFor( $journo['id'] );

    return $slowdata;
}




function journo_emitAllArticles( &$journo )
{

    $sql = <<<EOT
SELECT a.id,a.title,a.description,a.pubdate,a.permalink, o.prettyname as srcorgname, a.srcorg,a.total_bloglinks,a.total_comments
    FROM article a
        INNER JOIN journo_attr attr ON a.id=attr.article_id
        INNER JOIN organisation o ON o.id=a.srcorg
    WHERE a.status='a' AND attr.journo_id=?
    ORDER BY a.pubdate DESC
EOT;

    $arts = db_getAll( $sql, $journo['id'] );

    /* augment results with pretty formatted date and buzz info */
    foreach( $arts as &$a ) {
        $d = new datetime( $a['pubdate'] );
        $a['pretty_pubdate'] = pretty_date(strtotime($a['pubdate']));
        $a['iso_pubdate'] = $d->format('c');
        $a['buzz'] = BuzzFragment( $a );
    }
    /* sigh... php trainwreck. without this unset, the last element in array gets blatted-over
      if we again use $a in a foreach loop. Which we do.
      Because $a is still referencing last element. Gah.
      see  http://bugs.php.net/bug.php?id=29992 for gory details. */
    unset($a);

?>
<div class="box">
 <h2>Articles by <a href="/<?php echo $journo['ref']; ?>"><?php echo $journo['prettyname']; ?></a></h2>
 <div class="box-content">
  <p><?php echo sizeof($arts); ?> articles:</p>
  <ul class="art-list">


<?php unset($a); foreach( $arts as $a ) { ?>
    <li class="hentry">
        <h4 class="entry-title"><a href="<?php echo article_url($a['id']);?>"><?php echo $a['title']; ?></a></h4>
        <span class="publication"><?php echo $a['srcorgname']; ?>,</span>
        <abbr class="published" title="<?php echo $a['iso_pubdate']; ?>"><?php echo $a['pretty_pubdate']; ?></abbr>
        <?php if( $a['buzz'] ) { ?> (<?php echo $a['buzz']; ?>)<?php } ?><br/>
        <div class="art-info">
          <a class="extlink" href="<?php echo $a['permalink'];?>" >Original article at <?php echo $a['srcorgname']?></a><br/>
        </div>
    </li>
<?php } ?>

  </ul>

  <p>Article(s) missing? If you notice an article is missing,
  <a href="/missing?j=<?php echo $journo['ref'];?>">click here</a></p>
 </div>
</div>
<?php
}


?>
