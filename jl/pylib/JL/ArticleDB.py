import sys
import re
from datetime import datetime

import DB
import Journo
import Byline
import ukmedia
import Tags

class Error(Exception):
    pass

class FieldNotUnicodeError(Error):
    def __init__(self, fieldname):
        self.fieldname = fieldname
    def __str__(self):
        return repr(self.fieldname)


class ArticleDB:
    """Interface to Articles database

    Fields passed for an article:
    permalink - permalink to original article (hopefully!)
    pubdate - when article was posted (datetime)
    title - headline (raw text, no HTML)
    content - main text (basic HTML, escaped)
    description - summary of article (raw text, no HTML)
    byline - full byline text (optional, raw text, no HTML)
    srcorgname - which organisation published the article
    srcid - unique identifier within organisation (eg url)
    firstseen -
    lastseen -
    text, title, content, description, byline should all be unicode
    """

    def __init__(self):
        self.conn = DB.Connect()

        c = self.conn.cursor()
        c.execute( "SELECT id,shortname FROM organisation" )
        self.orgmap = {}
        while 1:
            row=c.fetchone()
            if not row:
                break
            self.orgmap[ row[1] ] = row[0]

    def Add( self, art ):
        """Store an article in the DB

        returns id of newly-added article
        """

        CheckArticle( art )

        # send text to the DB as utf-8
        title = art['title'].encode( 'utf-8' )
        byline = art[ 'byline' ].encode( 'utf-8' )
        description = art['description'].encode( 'utf-8' )
        pubdate = "%s" %(art['pubdate'])
        lastscraped = "%s" % (art['lastscraped'])
        lastseen = "%s" % (art['lastseen'])
        firstseen = lastseen    # it's a new entry
        content = art['content'].encode( 'utf-8' )
        srcurl = art['srcurl']
        permalink = art['permalink']
        srcorg = self.orgmap[ art[ 'srcorgname' ] ]
        srcid = art['srcid']


        # noddy wordcount
        txt = ukmedia.StripHTML( art['content'] )
        wordcount = len( txt.split() );

        # send to db!
        cursor = self.conn.cursor()
        q = 'INSERT INTO article (title, byline, description, lastscraped, pubdate, firstseen, lastseen, content, permalink, srcurl, srcorg, srcid, wordcount) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)'
        cursor.execute( q, ( title, byline, description, lastscraped, pubdate, firstseen, lastseen, content, permalink, srcurl, srcorg, srcid, wordcount ) )

        cursor.execute( "select currval('article_id_seq')" )
        id = cursor.fetchone()[0]
        cursor.close()

        Tags.Generate( self.conn, id, art['content'] )


        # parse byline to assign/create journos
        ProcessByline( self.conn, id, art['byline'], srcorg )
        self.conn.commit()

        ukmedia.DBUG2( u"%s: [a%s '%s'] (%s)\n" % (art['srcorgname'], id, art['title'], art['byline']) );

        # TODO: rollback on error!
        return id


    def ArticleExists( self, srcid ):
        """returns article id, if article is already in the DB"""
        article_id = None
        cursor = self.conn.cursor()
        q = 'SELECT id FROM article WHERE srcid=%s'
        cursor.execute( q, ( srcid ) )
        r = cursor.fetchone()
        if r:
            article_id = r[0]
        cursor.close()

        return article_id



class DummyArticleDB:
    """stub for testing"""

    def __init__(self):
        self.id = 1


    def Add( self, art ):
        CheckArticle( art )

        # just display article in a readable(ish) form
        ukmedia.PrettyDump( art )

        artid = self.id

        self.id = artid + 1
        ukmedia.DBUG2( u"%s: '%s' (%s)\n" % (art['srcorgname'], art['title'], art['byline']) );
        return artid

    def ArticleExists( self, srcid ):
        return 0



def CheckArticle(art):
    tagpat = re.compile( "<.*?>", re.UNICODE )
#   entpat = re.compile( "&((\w\w+)|(#[0-9]+)|(#[xX][0-9a-fA-F]+));", re.UNICODE )

    # check for missing/null fields
    for f in ('title','description','content', 'permalink', 'srcurl','srcid','srcorgname','lastscraped','pubdate' ):
        if not (f in art):
            raise Exception, ( "missing '%s' field!" % (f) )
        if not art[f]:
            raise Exception, ( "null '%s' field!" % (f) )

    # check for empty strings
    for f in ('title','description','content', 'permalink', 'srcurl','srcid' ):
        s= art[f]
        if s.strip() == u'':
            raise Exception, ( "blank '%s' field!" % (f) )


#   print "CheckArticle byline: ["+art['byline']+"]"
    # make sure assorted fields are unicode
    for f in ( 'title', 'byline', 'description', 'content' ):   #, 'permalink', 'srcurl','srcid' ):
        if not isinstance( art[f], unicode ):
            raise FieldNotUnicodeError(f)

    # check title and byline are single-line
    for f in ( 'title','byline' ):
        s = art[f]
        if s != s.strip():
            raise Exception, ( "%s has leading/trailing whitespace ('%s')" % (f,s.encode('latin-1','replace')) )
        if s.find("\n") != -1:
            raise Exception, ( "multi-line %s ('%s')" % (f,s.encode('latin-1','replace')) )

    # check for unwanted html tags & entities
    for f in ( 'title','byline','description' ):
        s = art[f]
        if s != ukmedia.DescapeHTML( s ):
            raise Exception, ( "%s contains html entities ('%s')" % (f,s.encode('latin-1','replace')) )
        if tagpat.search( s ):
            raise Exception, ( "%s contains html tags ('%s')" % (f,s.encode('latin-1','replace')) )
    
    # fix link URLs, then check for relative links
    for f in ('content', 'bio'):
        if f in art:
            art[f] = FixLinkURLs(art[f])
            for link in re.findall(r'''href\s*=\s*['"](.*?)['"]''', art[f]):
                link = link.strip()
                if link and not re.match(r'https?://|mailto:', link):
#                   raise Exception("%s contains relative links ('%s')" %
#                                   (f, 'href="%s"' % link.encode('latin-1','replace')))
                    ukmedia.DBUG("%s contains relative links ('%s')\n" %
                                    (f, 'href="%s"' % link.encode('latin-1','replace')))


def FixLinkURLs(html):
    """ Fix common errors in URLs in hyperlinks. """
    # Using regexps works better than BeautifulSoup for this.
    
    def fixup(match):
        url = match.group(1) or match.group(2) or ''
        url = re.sub('http:/(?=[^/])', 'http://', url)
        url = re.sub('https:/(?=[^/])', 'https://', url)
        url = re.sub(r'e?mail\s*to[:=]\s*', 'mailto:', url)
        url = re.sub(r'^(?=[a-zA-Z\.]+@[a-zA-Z\.]+)', 'mailto:', url)
        url = re.sub(r'^(?:http\.)?www\.', 'http://www.', url)
        return 'href="%s"' % url
    
    return re.sub(r'''href\s*=\s*(?:['"](.*?)['"]|(\S*?)(?=\>))''', fixup, html)


def ProcessByline( conn, article_id, byline, srcorgid ):
    """ Parse byline and assign to journos (creates journos along the way) """
    details = Byline.CrackByline( byline )
    if details is None:
        return None

    attributed = []

    # reminder: a byline can contain multiple journos
    for d in details:
        journo_id = StoreJourno(conn, d['name'], srcorgid)
        
        # credit journo with writing this article
        Journo.AttributeArticle( conn, journo_id, article_id )

        attributed.append( journo_id )

        if d.has_key('title'):
            Journo.SeenJobTitle( conn, journo_id, d['title'], datetime.now(), srcorgid )

    return attributed

def StoreJourno(conn, name, srcorgid):
    '''
    Stores the named journo if not already present.
    '''
    journo_id = Journo.FindJourno( conn, name, srcorgid )
    if not journo_id:
        journo_id = Journo.CreateNewJourno( conn, name )
        ukmedia.DBUG2( " NEW journo [j%s '%s']\n" % (journo_id, name) )
    return journo_id
