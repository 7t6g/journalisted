#!/usr/bin/env python2.4
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)

import getopt
import re
from datetime import datetime
import sys

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import ArticleDB,ukmedia

# sources used by FindArticles
rssfeeds = {
	'News': 'http://news.independent.co.uk/index.jsp?service=rss',
	'Independent.co.uk/Comment': 'http://comment.independent.co.uk/index.jsp?service=rss',
}




def Extract( html, context ):
	"""Parse the html of a single article

	html -- the article html
	context -- any extra info we have about the article (from the rss feed)
	"""

	art = context

	soup = BeautifulSoup( html )

	articlediv = soup.find( 'div', { 'class':'article' } )

	headline = articlediv.find( 'h1' )
	for cruft in headline.findAll( 'span' ):
		cruft.extract()
	art[ 'title' ] = headline.renderContents(None).strip()
	art[ 'title' ] = ukmedia.FromHTML( art['title'] )

	bylinepart = articlediv.find( 'h3' )
	if bylinepart:
		byline = bylinepart.renderContents(None).strip()
	else:
		byline = u''

	if byline == u'' and art['srcurl'].startswith( 'http://comment.independent.co.uk' ):
		# comment pages - if byline is empty, try and get it from title
		# eg "Janet Street-Porter: Our politicians know nothing of real life"
		m = re.match( "([\\w\\-']+\\s+[\\w\\-']+(\\s+[\\w\\-']+)?\\s*):", art['title'], re.UNICODE )
		if m:
			byline = m.group(1)
			# cull out duds
			if byline.lower() in ( u'leading article', u'the third leader' ):
				byline = u''

	art[ 'byline' ] = ukmedia.FromHTML( byline )

	pubdate = articlediv.find( 'h4' )
	art[ 'pubdate' ] = CrackDate( pubdate.renderContents() )

	body = articlediv.find( 'div', id='bodyCopyContent' )
	art['content'] = body.renderContents( None )
	art['content'] = ukmedia.SanitiseHTML( art['content'] )

	return art




def CrackDate( raw ):
	""" return datetime, or None if matching fails
	
	example date string: 'Published:&nbsp;01 September 2006'
	"""

	datepat = re.compile( '([0-9]{2})\s+(\w+)\s+([0-9]{4})' )
	m = datepat.search( raw )
	if not m:
		return None
	day = int( m.group(1) )
	month = ukmedia.MonthNumber( m.group(2) )
	year = int( m.group(3) )

	return datetime( year,month,day )




# pattern for scubbing <p> and <b> out of description text
descscrubpat = re.compile( u'</?[pb]>', re.UNICODE );

def ScrubFunc( context, entry ):
	""" description contains <p>, <b> etc...  scrub it! """
	context['description'] = descscrubpat.sub( u'', context['description'] ).strip()
	return context




def main():
	opts, args = getopt.getopt(sys.argv[1:], "h", ["help"])

	found = ukmedia.FindArticlesFromRSS( rssfeeds, u'independent', ScrubFunc )

	store = ArticleDB.ArticleDB()
	ukmedia.ProcessArticles( found, store, Extract )
#	for f in found:
#		print ("%s" % ( f['title'] )).encode( "utf-8" )

	return 0

if __name__ == "__main__":
    sys.exit(main())

