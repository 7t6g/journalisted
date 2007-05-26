#!/usr/bin/env python2.4

import re
from datetime import datetime
import sys

sys.path.append("../pylib")
import BeautifulSoup
from JL import ArticleDB,ukmedia


# The times RSS feeds seem a bit rubbish, and only have 5 articles
# each at anytime.
# So better bet is to scrape links from the pages. The times has a page for
# each days edition which contains links to all the headlines for that day.
# That's what we want.




linkpat = re.compile( '^/.*?/article[0-9]+\.ece$' )

# lots of links on the page which we don't want, so we'll
# look for sections with links we _do_ want...
sectionnames = ('News',
		'incomingFeeds',
		'Comment',
		'Business',
#		'Sport'
#		'Life &amp; Style',
#		'Arts &amp; Entertainment',
		)

siteroot = "http://timesonline.co.uk"


def FindArticles():

	ukmedia.DBUG2( "*** times ***: looking for articles...\n" )
	foundarticles = []

	# hit the page which shows the covers of the papers for the week
	# and extract a link to each day
	ukmedia.DBUG2( "fetching /tol/newspapers/the_times...\n" )
	html = ukmedia.FetchURL( siteroot + '/tol/newspapers/the_times' )
#	ukmedia.DBUG2( "  got it.\n" )
	soup = BeautifulSoup.BeautifulSoup(html)

	# (one day of the week will always be missing, as it'll have
	# been renamed 'Today')
	days = ( 'Today', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'The Sunday Times' )

	daypages = {}
	for link in soup.findAll( 'a', {'class':"link-06c"} ):
		day = link.renderContents().strip()
		if day in days:
			daypages[day] = siteroot + link['href']

	# go through each days page and extract links to articles
	for day, url in daypages.iteritems():

		ukmedia.DBUG2( "fetching " + day + "\n" )
		html = ukmedia.FetchURL( url )
#		ukmedia.DBUG( " got " + day + "\n" )
		fetchtime = datetime.now()
		soup = BeautifulSoup.BeautifulSoup(html)

		pagetitle = soup.find( 'title' )
		if pagetitle.find( text=re.compile('Sunday Times') ):
			srcorgname = "sundaytimes"
		else:
			srcorgname = "times"
		#print "** PAPER: " + srcorgname

		# go through by section
		for heading in soup.findAll( 'h3', {'class': 'section-heading' } ):
			sectionname = heading.find( text = sectionnames )
			if not sectionname:
				continue

			#ukmedia.DBUG( "  " + sectionname + "\n" )

			ul = heading.findNextSibling( 'ul' )
			for a in ul.findAll( 'a' ):
				title = ukmedia.DescapeHTML( a.renderContents(None) )
				url = siteroot + a['href']

				context = {
					'title': title,
					'srcurl': url,
					'srcid': url,
					'permalink': url,
					'lastseen': fetchtime,
					'srcorgname' : srcorgname,
					}

				foundarticles.append( context )

	ukmedia.DBUG2( "Found %d articles\n" % ( len(foundarticles) ) )
	return foundarticles



# work out a unique id for this url
# (must be unique across the times)
def CalcSrcID( url ):

	# URL is of form:
	#   http://www.timesonline.co.uk/article/0,,378-2335932,00.html
	# big number is id.
	
	idpat = re.compile( 'article/.*?,.*?,[0-9]+-([0-9]+),[0-9]*\.html' )
	m = idpat.search( url )
	return m.group(1)



def Extract( html, context ):

	art = context
	soup = BeautifulSoup.BeautifulSoup( html )

	h1 = soup.find( 'h1', {'class':'heading'} )
	art['title'] = h1.renderContents(None).strip()
	art['title'] = ukmedia.DescapeHTML( ukmedia.StripHTML( art['title'] ) )

	# times stuffs up bylines for obituaries (used for date span instead)
	if art['srcurl'].find( '/obituaries/' ) != -1:
		art['byline'] = u''
	else:
		authdiv = soup.find( 'div', {'class':'article-author'} )
		byline = authdiv.find( 'span', { 'class': 'byline' } )
		if byline:
			art['byline'] = byline.renderContents( None )
			art['byline'] = ukmedia.StripHTML( art['byline'] )
			art['byline'] = ukmedia.DescapeHTML( art['byline'] ).strip()
		else:
			art['byline'] = byline = u''

	paginationstart = soup.find( text=re.compile('^\s*Pagination\s*$') )
	paginationend = soup.find( text=re.compile('^\s*End of pagination\s*$') )

	if not paginationstart:
		raise Exception, "couldn't find start of main text!"
	if not paginationend:
		raise Exception, "couldn't find end of main text!"



	contentsoup = BeautifulSoup.BeautifulSoup()
	p = paginationstart.nextSibling
	while p != paginationend:
		next = p.nextSibling
		if not isinstance( p, BeautifulSoup.Comment ):
			contentsoup.insert( len(contentsoup.contents), p )
		p = next


	for cruft in contentsoup.findAll( 'div', {'class':'float-left related-attachements-container' } ):
		cruft.extract()
	for cruft in contentsoup.findAll( 'script' ):
		cruft.extract()
	#...more?

	art['content'] = ukmedia.SanitiseHTML( contentsoup.prettify(None) )

	# description is in a meta tag
	descmeta = soup.find('meta', {'name':'Description'} )
	desc = descmeta['content']
	desc = ukmedia.DescapeHTML( desc )
	desc = ukmedia.RemoveTags( desc )
	art['description' ] = desc

	# There is some javascript with a likely-looking pubdate:
	# """ var pubDate = new Date("Mar 3, 2007 12:00 AM")"""
	#
	# UPDATE: new format seems to be: '09-Apr-2007 00:00'

	datepat = re.compile( u"""\s*var pubDate = new Date\("(.*?)"\)""", re.UNICODE )

	m = datepat.search(html)
	art['pubdate'] = ukmedia.ParseDateTime( m.group(1) )

	return art



def main():


	found = FindArticles()
	store = ArticleDB.ArticleDB()
	ukmedia.ProcessArticles( found, store, Extract )

	return 0

if __name__ == "__main__":
    sys.exit(main())

