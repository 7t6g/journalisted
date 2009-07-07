<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../../phplib/db.php';

page_header( "Donate" );
/*
<div class="greenbox">
Journa<i>listed</i> is an independent, non-profit site run by the <a class="extlink" href="http://mediastandardstrust.org">Media Standards Trust</a> to help the public navigate the news
</div>
*/
?>
<div id="maincolumn">

  <div class="box">
  <h2>Donate</h2>
  <div class="box-content">
  <p><em>Do you want to help keep Journa<i>listed</i> going?</em></p>
  <p>Journa<i>listed</i> is an independent, non-profit service run by the Media Standards Trust.</p>
  <p>It relies on the support of donations from foundations and individuals.</p>
  <p>More than 100,000 people use Journa<i>listed</i> each month. One of the reasons it’s so useful is because it’s free. But it’s not free for us to run.</p>
  <p>For just £10 you can keep Journa<i>listed</i> running for an hour.</p>
  <p>For £20 a month you can help keep Journa<i>listed</i> a going concern.</p>
<?php donatebutton_emit( 
"Make a donation to the Media Standards Trust to run Journa<i>listed</i> (via JustGiving)", "http://www.justgiving.com/mediastandardstrust" ); ?>

  </div>
  </div>
</div>


<div id="smallcolumn">
  <div class="box">
    <h3>FAQs</h3>
    <div class="box-content">
      <ul>
        <li><a href="/faq/who-runs-journalisted">Who runs Journa<i>listed</i>?</a></li>
        <li><a href="/faq/how-is-journalisted-funded">How is Journa<i>listed</i> funded?</a></li>
      </ul>
      <div class="box-action"><a href="/faq">See all FAQs</a></div>
      <div style="clear: both;"></div>
    </div>
  </div>
</div>
<?php

page_footer();

?>
