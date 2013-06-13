import rethinkdb as r
import sched
import time
import urllib2
import json
import sys


WATCHED_REPO = [{
        "url": "mongodb/mongo",
        "name": "mongo"
    }, {
        "url": "rethinkdb/rethinkdb",
        "name": "rethinkdb"
    }, {
        "url": "basho/riak",
        "name": "riak"
    }, {
        "url": "ravendb/ravendb",
        "name": "ravendb"
    }, {
        "url": "apache/couchdb",
        "name": "couchdb"
    }, {
        "url": "apache/cassandra",
        "name": "cassandra"
    }]
INTERVAL = 60*60

def connect():
    # Open connection
    r.connect().repl()

    # Create db/table. If they already exist, an error will be returned, but it's fine, we can keep going.
    try:
        r.db_create("cakemeter").run()
    except r.errors.RqlRuntimeError as e:
        print "Database `cakemeter` not created."
        print str(e)
    else:
        print "Database `cakemeter` created"

    try:
        r.db("cakemeter").table_create("stars").run()
    except r.errors.RqlRuntimeError as e:
        print "Table `stars` in `cakemeter` not created."
        print str(e)
    else:
        print "Table `stars` in `cakemeter` created"


def initialize_scheduler():
    return sched.scheduler(time.time, time.sleep)

def schedule(scheduler, fn):
    print "Start fetching data"
    fn(scheduler, fn)
    scheduler.run()


def fetch(scheduler, fn):
    for repo in WATCHED_REPO:
        try:
            content_raw = urllib2.urlopen("https://api.github.com/repos/"+repo["url"]).read()
            content_json = json.loads(content_raw)

            r.db("cakemeter").table("stars").insert({
                "time": time.time(),
                "repo": repo["name"],
                "stars": content_json["watchers_count"]
            }).run()
        except urllib2.HTTPError, e:
            print "Could not get content for https://api.github.com/repos/"+repo["url"]
            print 'Error returned: %s.' % e.code
        except:
            print "Could not get content for https://api.github.com/repos/"+repo["url"]
            print "Not parsed error"


    scheduler.enter(INTERVAL, 1, fn, [scheduler, fn])


if __name__ == "__main__":
    connect()
    scheduler = initialize_scheduler()
    schedule(scheduler, fetch)

