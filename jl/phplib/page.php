<?php

require_once '../conf/general';
require_once '../../phplib/person.php';
require_once 'gatso.php';
require_once 'misc.php';

function page_header( $title, $params=array() )
{
	header( 'Content-Type: text/html; charset=utf-8' );

	if( $title )
		$title .= ' - ' . OPTION_WEB_DOMAIN;
	else
		$title = OPTION_WEB_DOMAIN;

    $P = person_if_signed_on(true); /* Don't renew any login cookie. */

	$datestring = date( 'l d.m.Y' );

	$mnpage = array_key_exists('menupage', $params) ? $params['menupage'] : '';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
  <title><?=$title ?></title>
  <style type="text/css" media="all">@import "/style.css";</style>
  <style type="text/css" media="all">@import "/text.css";</style>	
  <meta name="Content-Type" content="text/html; charset=UTF-8" />
<?php

	print "<!-- menupage: '$mnpage' -->\n";
	if (array_key_exists('rss', $params))
	{
		foreach ($params['rss'] as $rss_title => $rss_url)
		{
			printf( "  <link rel=\"alternate\" type=\"application/rss+xml\" title=\"%s\" href=\"%s\">\n", $rss_title, $rss_url );
		}
	}

	$js = array( "/jl.js" );
	if (array_key_exists('js_extra', $params))
	{
		//print_r( $params['js_extra'] );
		$js = array_merge( $js, $params['js_extra'] );
	}

	foreach( $js as $s ) {
//		print $s;
  		printf("  <script type=\"text/javascript\" src=\"%s\"></script>\n",$s);
	}

	
	if (array_key_exists('head_extra', $params)) {
		print $params['head_extra'];
	}
?>

</head>

<body>

	<div id="head">
		<h1><a href="/"><span></span>Journalisted</a></h1>
		<h2>&#0133;read all about them!</h2>
		<p>
			<strong>FREE!</strong><br />
			<?php echo $datestring; ?><br />
		</p>
	</div>

	<div id="menu">
		<span class="mst"><a href="http://www.mediastandardstrust.com">Media Standards Trust</a></span>
		<ul>
			<li class="cover<?php echo $mnpage=='cover' ? ' active' :''; ?>">
				<a href="/">Home</a>
			</li>
			<li class="all<?php echo $mnpage=='all' ? ' active' :''; ?>">
				<a href="/list">All Journalists</a>
			</li>
			<li class="subject<?php echo $mnpage=='subject' ? ' active' :''; ?>">
				<a href="/tags">Subject Index</a>
			</li>
			<li class="my<?php echo $mnpage=='my' ? ' active' :''; ?>">
				<a href="/alert">My Journa-list</a>
			</li>
			<li class="about<?php echo $mnpage=='about' ? ' active' :''; ?>">
				<a href="/about">About</a>
			</li>
		</ul>
	</div>



<?php
	if( $P )
	{
		if ($P->name_or_blank())
			$name = $P->name;
		else
			$name = $P->email;
		print "<div id=\"hellouser\">\n";
		print "Hello, {$name}\n";
		print "[<a href=\"/logout\">log out</a>] ";
		print "<small>(<a href=\"/logout\">this isn't you? click here</a>)</small><br>\n";
		print "</div>\n";
	}
?>


<div id="content" class="home">

<?php
}


function page_footer( $params=array() )
{

?>
<br clear="all" />
</div>
<div id="footer">
<?php

	gatso_report_html();

	$contactemail = OPTION_TEAM_EMAIL;
?>
<a href="/development">Development</a> |
<?=SafeMailto( $contactemail, 'Contact us' );?>
<br />
&copy; 2007 <a href="http://www.mediastandardstrust.com">Media Standards Trust</a><br />
</div>
</body>
</html>
<?php

//	debug_comment_timestamp();

}

?>
