#
#
#

from optparse import OptionParser
import sys
import socket
import traceback
import os
from datetime import datetime
import time

import ukmedia, ArticleDB
import feedparser

def RunMain( findarticles_fn, contextfromurl_fn, extract_fn, post_fn=None, maxerrors=20,
             prepare_parser_fn=None, after_parsing_fn=None ):
    """A generic(ish) main function that all scrapers can use

    Scrapers pass in callbacks:
    findarticles_fn: return a list of article contexts for a full scrape
    contextfromurl_fn: create an article context from a bare url
    extract_fn: function to process an HTML page and return an article
    post_fn(id, context): function to call after inserting an article into the database
    prepare_parser_fn(parser): function that adds any additional options to parser
    after_parsing_fn(options, args): function that returns adjusted (options, args).
    """

    parser = OptionParser()
    parser.add_option( "-u", "--url", dest="url", help="scrape a single article from URL", metavar="URL" )
    parser.add_option("-d", "--dryrun", action="store_true", dest="dryrun", help="don't touch the database")

    if prepare_parser_fn:
        prepare_parser_fn(parser)
    
    (options, args) = parser.parse_args()
    if after_parsing_fn:
        (options, args) = after_parsing_fn(options, args)

    found = []
    if options.url:
        context = contextfromurl_fn( options.url )
        found.append( context )
    else:
        found = found + findarticles_fn()

    if options.dryrun:
        store = ArticleDB.ArticleDB( dryrun=True, reallyverbose=True )  # testing
    else:
        store = ArticleDB.ArticleDB()

    # Hack: publish the store so that we can use its DB connection
    global article_store
    article_store = store
    
    ProcessArticles( found, store, extract_fn, post_fn, maxerrors )

    return 0

article_store = None





# Assorted scraping stuff
#

def FindArticlesFromRSS( rssfeeds, srcorgname, mungefunc=None ):
    """ Get a list of current articles from a set of RSS feeds """

    foundarticles = []

    socket.setdefaulttimeout( ukmedia.defaulttimeout )  # in seconds

    ukmedia.DBUG2( "*** %s ***: reading rss feeds...\n" % (srcorgname) )
    for feedname, feedurl in rssfeeds.iteritems():
        ukmedia.DBUG2( "feed '%s' (%s)\n" % (feedname,feedurl) )

        if ukmedia.USE_CACHE:
            ukmedia.FetchURL(feedurl, ukmedia.defaulttimeout, os.path.join( "rssCache", srcorgname ) )
            r = feedparser.parse( os.path.join( "rssCache", srcorgname, GetCacheFilename(feedurl) ) )
        else:
            r = feedparser.parse( feedurl )
        
        #debug:     print r.version;

        lastseen = datetime.now()
        for entry in r.entries:
            #Each item is a dictionary mapping properties to values
            url = entry.link        # will be guid if no link attr
            title = entry.title
            if hasattr(entry, 'summary'):
                desc = entry.summary
            else:
                desc = u''

            pubdate = None
            if hasattr(entry, 'updated_parsed'):
                if entry.updated_parsed:
                    pubdate = datetime.fromtimestamp(time.mktime(entry.updated_parsed))

#           print "New desc: ",desc.encode('latin-1','replace')

            title = ukmedia.DescapeHTML( title )
            desc = ukmedia.FromHTML( desc )
            

            context = {
                'srcid': url,
                'srcurl': url,
                'permalink': url,
                'description': desc,
                'title' :title,
                'srcorgname' : srcorgname,
                'lastseen': lastseen,
                'feedname': feedname #gtb
                }

            if pubdate:
                context['pubdate'] = pubdate

            if mungefunc:
                context = mungefunc( context, entry )
                # mungefunc can suppress by returning None.
                if not context:
                    continue
                if not context.get( 'srcid', None ):
                    ukmedia.DBUG2( "WARNING: missing/null srcid! ('%s')\n" % (context['srcurl']) )


            foundarticles.append( context )
    ukmedia.DBUG2( "found %d articles.\n" % ( len(foundarticles) ) )
    return foundarticles



def ShouldSkip( conn, srcid ):
    """ returns True if an article has a skip order on it (ie don't scrape!) """
    skip = False
    c = conn.cursor()
    c.execute( "SELECT * FROM error_articlescrape WHERE srcid=%s",(srcid) )
    row = c.fetchone()
    if row:
        if row['action'] == 's':
            skip = True
    c.close()
    return skip


def LogScraperError( conn, context, report ):
    c = conn.cursor()
    srcid = context['srcid']

    title = getattr(context, 'title', u'' )

    c.execute( "SELECT * FROM error_articlescrape WHERE srcid=%s", srcid )
    row = c.fetchone()
    if row:
        # article has an existing error entry...
        c.execute( "UPDATE error_articlescrape SET report=%s, attempts=attempts+1,lastattempt=NOW() WHERE srcid=%s", (report,srcid) )
    else:
        # log the error
        params = ( srcid, '', title, context['srcurl'], 1, report, ' ' )
        c.execute( "INSERT INTO error_articlescrape (srcid,scraper,title,srcurl,attempts,report,action,firstattempt,lastattempt) VALUES( %s,%s,%s,%s,%s,%s,%s,NOW(),NOW() )", params )
    c.commit()
    c.close()


def ProcessArticles( foundarticles, store, extractfn, postfn=None, maxerrors=10, showexisting=False ):
    """Download, scrape and load a list of articles

    Each entry in foundarticles must have at least:
        srcurl, srcorgname, srcid
    Assumes entire article can be grabbed from srcurl.

    extractfn - function to extract an article from the html
    postfn - option fn to call after article is added to db
    showexisting - if True, print a message when an article already is in DB
    """
    failcount = 0
    abortcount = 0
#   maxerrors = 10
    newcount = 0

    for context in foundarticles:

        conn = store.conn   # ugh...
        srcid = context['srcid']
        if not srcid:
            ukmedia.DBUG2( u"WARNING: missing srcid! '%s' (%s)\n" % (getattr(context,'title',''), context['srcurl'] ) )
            continue


        if ShouldSkip( conn, srcid ):
            ukmedia.DBUG2( u"s for skip: '%s' (%s)\n" % (getattr(context,'title',''), context['srcurl'] ) )
            continue

        try:
            article_id = store.ArticleExists( srcid )
            if article_id:
                if showexisting:
                    ukmedia.DBUG( u"already got '%s' - [a%s]\n" % (context['srcurl'], article_id ) )
                continue;   # skip it - we've already got it

            html = ukmedia.FetchURL( context['srcurl'], ukmedia.defaulttimeout, os.path.join( "cache", context['srcorgname'] ) )
 
            # some extra, last minute context :-)
            context[ 'lastscraped' ] = datetime.now()

            art = extractfn( html, context )

            if art:
                artid = store.Add( art )
                newcount = newcount + 1
                # if there is a post-processing fn, call it
                if postfn:
                    postfn( artid, art )


        except Exception, err:
            # always just bail out upon ctrl-c
            if isinstance( err, KeyboardInterrupt ):
                raise

            failcount = failcount+1
            # TODO: phase out NonFatal! just get scraper to print out a warning message instead
            if isinstance( err, ukmedia.NonFatal ):
                continue

            report = traceback.format_exc()

            if context.has_key( 'title' ):
                msg = u"FAILED: '%s' (%s):" % (context['title'], context['srcurl'])
            else:
                msg = u"FAILED: (%s):" % (context['srcurl'])
            print >>sys.stderr, msg.encode( 'utf-8' )
            print >>sys.stderr, '-'*60
            print >>sys.stderr, report
            print >>sys.stderr, '-'*60

            LogScraperError( conn, context, report )

            abortcount = abortcount + 1
            if abortcount >= maxerrors:
                print >>sys.stderr, "Too many errors - ABORTING"
                raise
            #else just skip this one and go on to the next...

    ukmedia.DBUG( "%s: %d new, %d failed\n" % (sys.argv[0], newcount, failcount ) )
    return (newcount,failcount)

