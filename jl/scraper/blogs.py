#!/usr/bin/env python2.4
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# Scraper for BBC News blogs site
#
# TODO:
#

import getopt
import re
from datetime import datetime
import sys
import time

#print sys.argv

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup, Comment
from JL import ArticleDB,ukmedia

#10	bbcnews	BBC News
#11	observer	The Observer
#12	sundaymirror	The Sunday Mirror
#13	sundaytelegraph	The Sunday Telegraph
#3	express	The Daily Express
#1	independent	The Independent
#2	dailymail	The Daily Mail
#4	guardian	The Guardian
#5	mirror	The Mirror
#6	sun	The Sun
#8	times	The Times
#9	sundaytimes	The Sunday Times
#7	telegraph	The Daily Telegraph

# sources used by FindArticles
rssfeedGroups = {

	# Times Online pattern		
	u'times':
	{
		'rssfeeds':
		{
			u'Charles Bremner':							'http://timescorrespondents.typepad.com/charles_bremner/rss.xml',	# 'http://timescorrespondents.typepad.com/charles_bremner/',
			u'Leo Lewis':								'http://timesonline.typepad.com/urban_dirt/rss.xml',				# 'http://timesonline.typepad.com/urban_dirt/',
			u'Ruth Gledhill':							'http://timescolumns.typepad.com/gledhill/rss.xml',
			u'Peter Stothard':							'http://www.timescolumns.typepad.com/stothard/rss.xml',
			u'David Aaronovitch':						'http://timesonline.typepad.com/david_aaronovitch/rss.xml',
			u'Anna Shepherd':							'http://timesonline.typepad.com/eco_worrier/rss.xml',
			u'Gerard Baker':							'http://timescorrespondents.typepad.com/baker/rss.xml'
		}
		,
		'regexp':
		[
			u'''
				(?:
					<meta\ name="description"\ content="
		    			(?P<author>[^,.]+)
			    	.*?
			    )?
			    (?:
			    	<h2\ id="banner-description">
			    		(?P<author2>[^,.]*)
			    		.*?
			    	</h2>
			    )?
		    	.*?
	    		<h2\ class="date-header">
					(?P<date>[^<]+)
				.*?
				<h3\ class="entry-header">
					(?P<title>[^<]+)
				.*?
				<div\ class="entry-body">
					(?P<content>.*?)
				<!--\ technorati\ tags\ -->
			'''
		]
	},
	u'guardian':
	{
		'rssfeeds':
		{
			u'(Guardian Blogs - various)':					'http://blogs.guardian.co.uk/atom.xml' 						# 'http://blogs.guardian.co.uk/index.html'
		},
		'regexp':
		[
			u'''
				<h2>
					(?P<author>[^<]+)
				</h2>
				.*?
				<h1>
					(?P<title>[^<]+)
				</h1>
				\s*
				<div\ class="blogs-article-excerpt">
					(?P<description>.*?)
					</div>
				.*?
				<div\ class="blogs-article-date">			
					(?P<date>[^<]+)
				.*?
				<div\ class="blogs-article-content">
					(?P<content>.*?)
				</div>
			''',
			u'''
				<h1>
					(?P<title>[^<]+)
				</h1>
				\s*
				<p\ class="standfirst">
					(?P<description>[^<]*)
				.*?
				<h2>
					(?:<a[^>]*>)?
						(?P<author>[^<]+)
				.*?
				<div\ id="twocolumnleftcolumntopbaselinetext">
					(?P<date>[^|<]+)
				.*?
				</div>
					(?P<content>.*?)
				(?:</div>|<small>)
			'''
		]
	},

	u'bbcnews':
	{
		'rssfeeds':
		{
		    u'(The Editors- split out by name)':	    'http://www.bbc.co.uk/blogs/theeditors/rss.xml',                # 'http://www.bbc.co.uk/blogs/theeditors/',
		    u'Evan Davies':	                            'http://www.bbc.co.uk/blogs/thereporters/evandavis/rss.xml',    # 'http://www.bbc.co.uk/blogs/thereporters/evandavis/',
		    u'(Five Live Breakfast-split out by name)':	'http://www.bbc.co.uk/blogs/fivelivebreakfast/index.xml',       # 'http://www.bbc.co.uk/blogs/fivelivebreakfast/',
		    u'Mark Mardell':	                        'http://www.bbc.co.uk/blogs/thereporters/markmardell/rss.xml',  # 'http://www.bbc.co.uk/blogs/thereporters/markmardell/',
		    u'Mihir Bose':	                            'http://www.bbc.co.uk/blogs/thereporters/mihirbose/rss.xml',    # 'http://www.bbc.co.uk/blogs/thereporters/mihirbose/',
		    u'Nick Robinson':	                        'http://blogs.bbc.co.uk/nickrobinson/rss.xml',                  # 'http://www.bbc.co.uk/blogs/nickrobinson/',
		    u'Mark Devenport':	                        'http://www.bbc.co.uk/blogs/thereporters/markdevenport/rss.xml',# 'http://www.bbc.co.uk/blogs/thereporters/markdevenport/',
		    u'Robert Peston':	                        'http://www.bbc.co.uk/blogs/thereporters/robertpeston/rss.xml',	# 'http://www.bbc.co.uk/blogs/thereporters/robertpeston/',
		    u'(PM Blog- Eddie Mair & others)':	        'http://www.bbc.co.uk/blogs/pm/index.xml',						# 'http://www.bbc.co.uk/blogs/pm/',
		    u'Martin Rosenbaum':	                    'http://www.bbc.co.uk/blogs/opensecrets/rss.xml',				# 'http://www.bbc.co.uk/blogs/opensecrets/',
		    u'Brian Taylor':	                        'http://www.bbc.co.uk/blogs/thereporters/briantaylor/rss.xml',	# 'http://www.bbc.co.uk/blogs/thereporters/briantaylor/',
		    u'(Sports editors blogs- Roger Mosey et al)':'http://www.bbc.co.uk/blogs/sporteditors/index.xml',			# 'http://www.bbc.co.uk/blogs/sporteditors/',
		    u'(Newsnight blog- Peter Barron et al)':	'http://www.bbc.co.uk/blogs/newsnight/index.xml',				# 'http://www.bbc.co.uk/blogs/newsnight/',
		    u'Betsan Powys':	                    	'http://www.bbc.co.uk/blogs/thereporters/betsanpowys/rss.xml',	# 'http://www.bbc.co.uk/blogs/thereporters/betsanpowys/',
		    u'(World Have Your Say- Ros Atkins et al)':	'http://blogs.bbc.co.uk/worldhaveyoursay/index.xml'				# 'http://www.bbc.co.uk/blogs/worldhaveyoursay/'
		},
		'regexp':
		[
			# BBC News blogs pattern:
			u'''
				<div\s+class="entry
				.*?
				<h[^>]*>
					\s*
					(?:<a[^>]*>)?
						(?P<title>[^<]+)
				.*?
				<li\s+class="author">
					\s*
					(?:<a[^>]*>)?
						(?P<author>[^<]+)
				.*?
				<li\s+class="date">
					(?P<date>[^<]+)
					</li>
					\s*
				</ul>
				\s*
				(?P<content>.*?)
				\s*
				(?:
					(?:</div>)
				|
					(?:<ul\ class="ami_social_bookmarks">)
				|
					(?:<p><strong>You\ can\ comment\ on\ this\ entry)
				)
			'''
		]
	},


	u'skynews':
	{
		'rssfeeds':
		{
			u'(Frontline blog- various journalists)':	'http://skynews6.typepad.com/my_weblog/index.rdf',
			u'(Adam Boulton & Co)':						'http://adamboulton.typepad.com/my_weblog/index.rdf', # 'http://adamboulton.typepad.com/',
			u'Martin Brunt':							'http://skynews4.typepad.com/my_weblog/index.rdf',
			u'(Editors blog- various editors)':			'http://skynews7.typepad.com/my_weblog/index.rdf',
			u'(Technology blogs- various)':				'http://skynews.typepad.com/technologyblog/index.rdf',
			u'Michael Wilson':							'http://www.skynews5.typepad.com/my_weblog/index.rdf',
			u'Paul Bromley':							'http://skynews3.typepad.com/my_weblog/index.rdf',
			u'Greg Milam':								'http://skynews8.typepad.com/my_weblog/index.rdf',
			u'Tim Marshall':							'http://martinstanford.typepad.com/foreign_matters/index.rdf',
		},
		'regexp':
		[
			# Sky News pattern:
			u'''
				<span\ class="entry_header">
					.*?
					<[bB]>
						(?P<title>[^<]+)
				.*?
				<span\ class="mainBlack">
					(?P<date>[^<]+)
					.*?
				<div\ class="entry-body">
				\s+
				(?:
					.*?
					<strong>
						\s*
						<img[^>]*>
						\s*
					</strong>
				)?
				(?:
					(?P<precontent>
						.*?
						<strong>
						(?:<a[^>]*>)?
						(?:<img[^>]*>)?
						(?:</a[^>]*>)?
					)
					(?P<author2>[^<]+)
				)?
				.*?
				(?P<content>.*?)
				(?:
					<strong>
					(?P<author>
						Posted By .*?
					)
					</strong>
				)?
				<div\ class="entry-comments"
			'''
		]
#		<strong>By\ Sky\ News\ 
#			([a-z ]+)
#			([^<]+)
	},

	u'telegraph':
	{
		'rssfeeds':
		{
			u'(Telegraph Blogs)':				'http://blogs.telegraph.co.uk/Feed.rss',	# 'http://blogs.telegraph.co.uk/',
# From Our Bloggers: http://blogs.telegraph.co.uk/
#<a href="/ukcorrespondents/"><h4>UK Correspondents</h4></a>
			u'Holy Smoke by Damian Thompson':	'http://blogs.telegraph.co.uk/ukcorrespondents/holysmoke/feed.rss',
			u'Christopher Howse on language': 'http://blogs.telegraph.co.uk/ukcorrespondents/christopherhowse/feed.rss',
			u'Andrew McKie Obituaries Editor': 'http://blogs.telegraph.co.uk/ukcorrespondents/andrewmckie/feed.rss',
			u'Neil Midgley on television': 'http://blogs.telegraph.co.uk/ukcorrespondents/neilmidgley/feed.rss',
			u'Home Truths': 'http://blogs.telegraph.co.uk/ukcorrespondents/hometruths/feed.rss',
			u'Julia Haileson the environment': 'http://blogs.telegraph.co.uk/ukcorrespondents/juliahailes/feed.rss',
			u'Web TV hits': 'http://blogs.telegraph.co.uk/ukcorrespondents/webtvhits/feed.rss',
			#<a href="/foreign/"><h4>Foreign Correspondents</h4></a>
			u'Catherine Elsworth in Los Angeles': 'http://blogs.telegraph.co.uk/foreign/catherineelsworth/feed.rss',
			u'Peter Fosterin New Delhi': 'http://blogs.telegraph.co.uk/foreign/peterfoster/feed.rss',
			u'Richard Spencer in Beijing': 'http://blogs.telegraph.co.uk/foreign/richardspencer/feed.rss',
			u'David Blair Diplomatic Correspondent': 'http://blogs.telegraph.co.uk/foreign/davidblair/feed.rss',
			u'Toby Harnden in Washington DC': 'http://blogs.telegraph.co.uk/foreign/tobyharnden/feed.rss',
			u'Harry de Quetteville in Berlin': 'http://blogs.telegraph.co.uk/foreign/harrydequetteville/feed.rss',
			u'Adrian Blomfield in Moscow': 'http://blogs.telegraph.co.uk/foreign/adrianblomfield/feed.rss',
			#<a href="/business/"><h4>Business</h4></a>
			u'Tales from the high streetby Fletcher and Hall': 'http://blogs.telegraph.co.uk/business/talesofthehighstreet/feed.rss',
			u'Your Business Blogby Richard Tyler': 'http://blogs.telegraph.co.uk/business/yourbusiness/feed.rss',
			u'Ambrose Evans-Pritchard': 'http://blogs.telegraph.co.uk/business/ambrosevanspritchard/feed.rss',
			u'Market forcesby Ben Bland': 'http://blogs.telegraph.co.uk/business/marketforces/feed.rss',
			#<a href="/technology/"><h4>Technology</h4></a>
			u'Shane Richmond': 'http://blogs.telegraph.co.uk/technology/shanerichmond/feed.rss',
			u'Ian Douglas': 'http://blogs.telegraph.co.uk/technology/iandouglas/feed.rss',
			#<a href="/politics/"><h4>Politics</h4></a>
			u'Gimson Unbound': 'http://blogs.telegraph.co.uk/politics/gimsonunbound/feed.rss',
			u'Daniel Hannan': 'http://blogs.telegraph.co.uk/politics/danielhannan/feed.rss',
			u'Christopher Hope': 'http://blogs.telegraph.co.uk/politics/christopherhope/feed.rss',
			u'Brassneck by Mick Fealty': 'http://blogs.telegraph.co.uk/politics/brassneck/feed.rss',
			#<a href="/arts/"><h4>Arts</h4></a>
			u'Ceri Radford': 'http://blogs.telegraph.co.uk/arts/ceriradford/feed.rss',
			u'Reel Life with Davies and Gray': 'http://blogs.telegraph.co.uk/arts/reellife/feed.rss',
			u'The Slaughtered Lamb by Sally Peck': 'http://blogs.telegraph.co.uk/arts/slaughteredlamb/feed.rss',
			u'Frame of mind by Lucy Davies': 'http://blogs.telegraph.co.uk/arts/frameofmind/feed.rss',
			#<a href="/sport/"><h4>Sport</h4></a>
			u'Mick Cleary on rugby': 'http://blogs.telegraph.co.uk/sport/mickcleary/feed.rss',
			u'Gareth A. Davies on boxing': 'http://blogs.telegraph.co.uk/sport/garethdavies/feed.rss',
			u'Kevin Garside on Formula 1': 'http://blogs.telegraph.co.uk/sport/kevingarside/feed.rss',
			u'Oliver Brown on football': 'http://blogs.telegraph.co.uk/sport/oliverbrown/feed.rss',
			u'In Sport': 'http://blogs.telegraph.co.uk/sport/insport/feed.rss',
			u'Fantasy Football': 'http://blogs.telegraph.co.uk/sport/fantasyfootball/feed.rss',
			u'Nick Houlton sporting history': 'http://blogs.telegraph.co.uk/sport/nickhoult/feed.rss',
			u'Patrick Nathanson': 'http://blogs.telegraph.co.uk/sport/patricknathanson/feed.rss',
			#<a href="/society/"><h4>Society</h4></a>
			u'Bryony Gordon': 'http://blogs.telegraph.co.uk/society/bryonygordon/feed.rss',
			#<a href="/travel/"><h4>Travel</h4></a>
			u'Francisca Kellett': 'http://blogs.telegraph.co.uk/travel/franciscakellett/feed.rss',
			u'Charles Starmer-Smith': 'http://blogs.telegraph.co.uk/travel/charlesstarmersmith/feed.rss',
			#<a href="/motoring/"><h4>Motoring</h4></a>
			u'Erin Baker': 'http://blogs.telegraph.co.uk/motoring/erinbaker/feed.rss',
			#<a href="/gardening/"><h4>Gardening</h4></a>
			u'The Rake\'s Progress by Lila Das Gupta': 'http://blogs.telegraph.co.uk/gardening/rakes-progress/'
		},
		'regexp':
		[
			u'''
				<h1>
					(?P<blogname>[^<]+)
				.*?
				(?:
					<h2>
						(?P<blogname2>[^<]+)
					.*?
				)?
				<div\ id="bhDescription"><p>
					(?P<author_description>[^<]+)
				.*?
				<h2>
					<a[^>]*>
						(?P<title>[^<]+)
					</a>
				.*?
				<div\ class="smalltext">
					Posted\ by\ 
					(?:<a[^>]*>)?
					(?P<author>[^<]+)
				.*?
				\ on\ (?P<date>[^<]+)
				.*?
				<div\ class="postDetails">
					(?P<content>.*?)
				</div>
			'''
		]
	},


	u'independent':
	{
		'rssfeeds':
		{
			u'Independent blogs (various)':				'http://indyblogs.typepad.com/independent/index.rdf' # 'http://indyblogs.typepad.com/',
		},
		'regexp':
		[
			# Indy pattern:
			u'''
				<h2\ class="date-header">
					(?P<date>[^<]+)
				</h2>
				.*?
				<h3\ class="entry-header">
					(?P<title>[^<]+)
				</h3>
				.*?
				<div\ class="entry-body">
					.*?
					>By\ (?P<author>[^<]+)
					.*?
				</a>
					(?P<content>.*?)
				<div\ class="entry-footer">	
			'''
		]
	},

	
	u'dailymail':
	{
		'rssfeeds':
		{
			u'Benedict Brogan':							'http://broganblog.dailymail.co.uk/rss.xml',
			u'Peter Hitchens':							'http://hitchensblog.mailonsunday.co.uk/rss.xml',
			u'Baz Bamigboye':							'http://bazblog.dailymail.co.uk/rss.xml',
			u'Katie Nicholl':							'http://katienicholl.mailonsunday.co.uk/rss.xml',
			u'Natalie Theo':							'http://fashionblog.dailymail.co.uk/rss.xml',
			u'Stephen Wright':							'http://bikeride.dailymail.co.uk/rss.xml',

			u'This is Money (various)':					'http://feeds.feedburner.com/ThisIsMoneyBlog'
#			'Daily Mail blogs (7 of them)':				'http://www.dailymail.co.uk/pages/live/blogs/dailymailblogs.html?in_page_id=1983'
		},
		'regexp':
		[
			# Daily Mail blogs pattern:
			u'''
				<h1>
					(?:<a[^>]*>)?
						(?P<title>[^<]+)
				</h1>
				\s*
				<span\s+class="artByline">by\s+
					(?P<author>[^<]+)
				.*?
				<span\s+class="artDate">
					Last\ updated\ at\s+(?P<date>[^<]+)			
				.*?
				Comments\s+\(\d+\)</a>
					(?P<content>.*?)
				<div\s+id="social_links_sub">
			''',
			u'''
				<h2\ class="date-header">
					(?P<date>[^<]+)
				</h2>
				.*?
				<h3\ class="entry-header">
					(?P<title>[^<]+)
				</h3>
				.*?
				<div\ class="entry-body">
					(?P<content>.*?)
				<(?:p|div)\ class="entry-footer">
				(?:
					.*?
					Author:\ (?P<author>[^<]+)
				)?
			'''
			# '''
		]
	}
	
}



def Extract( html, context ):

#	print context['srcurl']
	if context['srcurl'].find('/podcasts/')!=-1:
#		print "Ignoring podcast"
		# it's a podcast, we'll quietly ignore it
		return None


	"""Parse the html of a single article

	html -- the article html
	context -- any extra info we have about the article (from the rss feed)
	"""

	art = context

	soup = BeautifulSoup( html )

#	meta = soup.find( 'meta', { 'name': 'Headline' } )
#	art['title'] = ukmedia.DescapeHTML( meta[ 'content' ] ).strip()

#	meta = soup.find( 'meta', { 'name': 'OriginalPublicationDate' } )
#	art['pubdate'] = ukmedia.ParseDateTime( meta['content'] )

	# TODO: could use first paragraph for a more verbose description
#	meta = soup.find( 'meta', { 'name': 'Description' } )
#	art['description'] = ukmedia.DescapeHTML( meta[ 'content' ] ).strip()

	# byline
#	byline = u''
#	spanbyl = soup.find( 'span', {'class':'byl'} )
#	if spanbyl:	# eg "By Paul Rincon"
#		byline = spanbyl.renderContents(None).strip()
#	spanbyd = soup.find( 'span', {'class':'byd'} )
#	if spanbyd:	# eg "Science reporter, BBC News, Houston"
#		byline = byline + u', ' + spanbyd.renderContents(None).strip()
#	art['byline'] = ukmedia.FromHTML( byline )


#               <div class="entry" id="entry-18926">
#                   <img src="http://www.bbc.co.uk/blogs/theeditors/includes/images/peterhorrocks.jpg" border="0" width="58" height="55" alt="Peter Horrocks" style="border=0;float:left;padding:6px 0 0 10px;margin-right:10px;" />
#               <h3 style="clear:none;width:365px;"><a href="http://www.bbc.co.uk/blogs/theeditors/2007/10/flying_solo.html">Flying solo</a></h3>#
#				<ul class="entrydetails">
#					<li class="author"><a href="http://www.bbc.co.uk/blogs/theeditors/peter_horrocks/">Peter Horrocks</a></li>
#					<li class="date">15 Oct 07, 05:05 PM</li>
#				</ul><br clear="all" />
#				<p>We've ....
				
				
	# just use regexes to extract the article text
	txt = soup.renderContents(None)
#	m = re.search( u'<!--\s*S BO\s*-->(.*)<!--\s*E BO\s*-->', txt, re.UNICODE|re.DOTALL )
	
	


	# TODO strip weird non-ascii on date of telegraph


	# Use right pattern for the organisation:
	patterns = rssfeedGroups[context[u'srcorgname']]['regexp']
	
	for pattern in patterns:
		capturedPatternNames = []
		for capturedPatternName in re.finditer(u'\(\?P<([^>]+)>', pattern):
			capturedPatternNames.append(capturedPatternName.group(1))
	
	#	pattern = u'<a(.*)>'
	#	print pattern
	#	print txt.encode('latin-1','replace')
	
		timeBefore = time.time()
		m = re.search( pattern, txt, re.UNICODE|re.DOTALL|re.VERBOSE )
		timeAfter = time.time()
	
		# WARNING on slow regular expressions:
		if (timeAfter-timeBefore)>100:
			print "WARNING: Regular expression search took: ",1000*(timeAfter-timeBefore)

		if m:
			break;

	for fieldName in capturedPatternNames:
		fieldValue = ukmedia.GetGroup(m,fieldName)
	#, fieldValue in m:
#	for i in range(len(fieldOrder)):
#		print fieldOrder[i]
#		fieldValue = m.group(i+1)
#		fieldName = fieldOrder[i]
		if fieldValue:
			art[fieldName] = fieldValue.strip(" -\r\n") # strip extra - and spaces

#	art['title'] = m.group(1)
#	art['author'] = m.group(2)
#	art['date_unparsed'] = m.group(3)
#	art['content'] = m.group(4)



	# Now for Sky News:
	# The "byline" is sometimes hidden inside the text:
	#     The Politburo is blah blah blah
	#     But <strong>Sky News China correspondent Peter Sharp</strong> says there is a darker side to his legacy.
	# So we match before the byline as precontent, and if non-empty prepend it to content
	if ('precontent' in art):
		art['precontent'] = ukmedia.StripHTML( art['precontent'] ).strip(' \t\n')
		# print "PRECONTENT: ["+art['precontent'].encode('latin-1','replace')+"]"
		if art['precontent']!="":
			art['content'] = art['precontent']+art['author2']+art['content']
		del art['precontent']
	


	# fix everything up:
	art['content'] = ukmedia.SanitiseHTML( art['content'] )

	if (('author' in art) and re.search('\\bGuardian Unlimited\\b',art['author'])):
		del art['author']
	if ('author2' in art) and art['author2']==u'':
		del art['author2']


	# author should be two words or more
	# disabled: and no lower case words: not re.search('\\b[a-z]',art['author'])) and 
	# and one of the words is not 'The', as in "The Guardian"
	if ('author' in art) and re.search(' ',art['author']) and art['author']!=u'The Guardian':
#		print "AUTHOR: "+art['author']		
		True
	elif ('author2' in art):
		art['author'] = art['author2']		# sometimes author appears in just one of two places
		del art['author2']
	elif ('blogname' in art):
		art['author'] = art['blogname']
	# Feed name is author name if no brackets, i.e. if not "The Editors (split out by name)"
	elif (re.search('\(', context['feedname'])==None):
		art['author'] = context['feedname']	# maybe author is not written in page or in RSS, we just know it because of the URL
	elif ('author' in art) and (not re.search(' ',art['author'])):					# one word author pseudo-nym (e.g. Sequin)
		True
	else:
		# otherwise try extracting author from the first paragraph:
		author = ukmedia.ExtractAuthorFromParagraph(art['description'])
		if author!=u'':
			art['author'] = author
		else:
			# otherwise try using the first paragraph as the byline?
			art['author'] = ukmedia.FromHTML(art['description'])




	art['author'] = re.compile(u'\n').sub(' ', art['author'],re.UNICODE)	# get rid of newlines
	# change e.g. "DONNA McCONNELL" -> "Donna McConnell"
	def lower(s):
		return s.group(1)+s.group(2).lower()
	art['author'] = re.sub(u'([A-Z])([A-Z]+)', lower, art['author'], re.UNICODE|re.DOTALL)


	art['byline'] = art['author']
	
	
#	print "BYLINE: "+art['byline']		

#	if not ('description' in art):
		# we just use the description passed in (from the RSS feed)
#		art[ 'description' ] = ukmedia.FromHTML( art['description'] )


#	print "\n\nDATE: ",art['date'],"\n\n"

	# Parse date:
	art['pubdate'] = ukmedia.ParseDateTime( art['date'] )
	del art['date']	

	if 'description' in art:
		art['description'] = re.sub('&Soul;','&Soul',art['description']) # gtb!hack! to fix feed parser stuffing up?
		
#		print "Description before tidying: ",art['description'].encode('latin-1','replace')
		art['description'] = ukmedia.FromHTML(art['description'])	# sometimes there's the odd <b>BLAH BLAH</b> bit
#		print "Tidied description: ",art['description'].encode('latin-1','replace')

	if False: # True:		# debug
		print "\n\nARTICLE (+RSS CONTEXT) FIELDS:"
		for a in art.keys():
			# hack:
			print "\n",a,": ",
			if type(art[a])==type(u""):
				print art[a].encode('latin-1','replace')
			else:
				print str(art[a])

	return art




# bbc news rss feeds have lots of blogs and other things in them which
# we don't parse here. We identify news articles by the number in their
# url.
#idpat = re.compile( '/(\d+)\.stm$' )

def ScrubFunc( context, entry ):
#	print context;	

	if False:
		print u"\n"
		print u"--------------------------------------------------------------------"
		print u"ARTICLE CONTEXT:"
		print u"\n"
		for key in context.keys():
			s = repr(context[key])
			print key.encode('latin-1','replace'),': ',s.encode('latin-1','replace')
		print u"\n"
		print u"ARTICLE RSS FIELDS:"
		print u"\n"
		for key in entry.keys():
			s = repr(entry[key])
			print key.encode('latin-1','replace'),': ',s.encode('latin-1','replace')

#	m = idpat.search( context['srcurl'] )
#	if not m:
#		ukmedia.DBUG2( "SUPPRESS " + context['title'] + " -- " + context['srcurl'] + "\n" )
#		return None		# suppress this article (probably a blog)

	# Also we use this number as the unique id for the beeb, as a story
	# can have multiple paths (eg uk vs international version)
	
	# gtb:
	# for blogs just use URL:
	context['srcid'] = context['srcurl'] # m.group(1)

	return context


def main():
	opts, args = getopt.getopt(sys.argv[1:], "h", ["help"])
	
	DEBUG_SINGLE_TEST_CASE = False # 

	# TODO: filter out "Your Stories" page
	rssfeedGroupsToProcess = rssfeedGroups


#	DEBUG_JUST_DO_GROUPS = {u'times'} # False  # u'guardian'	# False # u'dailymail'	# was False
	DEBUG_JUST_DO_GROUPS = False
	
	rssfeedGroupsToProcess = rssfeedGroups.keys()
	if DEBUG_JUST_DO_GROUPS:
		rssfeedGroupsToProcess = DEBUG_JUST_DO_GROUPS
#	print len(sys.argv)
	
	# allow user to specify what newspapers to do on the commandline, e.g. "blogs.py times skynews"
	if len(sys.argv)>1:
		rssfeedGroupsToProcess = sys.argv
		rssfeedGroupsToProcess.pop(0) # 0th element is "blogs.py" so get rid of it
		
				
	for rssfeedGroupName in rssfeedGroupsToProcess:
		rssfeedGroup = rssfeedGroups[rssfeedGroupName]

		DEBUG_OUTPUT_TO_DIR = False
		if DEBUG_OUTPUT_TO_DIR:
			if not os.path.exists("output"):
				os.mkdir("output")
			sys.stdout = open("output/blogs_"+rssfeedGroupName+".txt", 'w')
			sys.stderr = sys.stdout

		print "RSSFEED_GROUP: ",rssfeedGroupName
		# e.g. rssfeedGroupName = u'bbcnews'

		if DEBUG_SINGLE_TEST_CASE:
			# TEST CASES:
			filename = "webpageExamples/"+rssfeedGroupName+".html"
			f = open(filename, "rb")
			html = f.read()
			f.close()
			context = {
				u'srcorgname': rssfeedGroupName, 
				u'feedname': u"Author Name", 
				u'description': u"test desc",
				u'permalink': u"localhost",
				u'srcurl': u"localhost",
				u'srcid': u"srcid"
			}
			
			art = Extract(html, context)
			store = ArticleDB.DummyArticleDB()	# testing
			artid = store.Add( art )
		else:
			rssfeeds = rssfeedGroup['rssfeeds']
			found = ukmedia.FindArticlesFromRSS( rssfeeds, rssfeedGroupName, ScrubFunc )

#			print "\nFOUND:\n"
#			for f in found:
#				print ("%s" % ( f['title'] )).encode( "utf-8" )
			if False:
				print "\n--------------------------\nFOUND IN DETAIL:\n"
				for f in found:
					print ("%s" % ( f )).encode( "utf-8" )
				print "\n--------------------------\n"
			# store = ArticleDB.ArticleDB()
			store = ArticleDB.DummyArticleDB()	# testing
			ukmedia.ProcessArticles( found, store, Extract )
		
	return 0

if __name__ == "__main__":
    sys.exit(main())

