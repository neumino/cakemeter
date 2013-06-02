cakemeter
=========

Trolling around :-)



What to expect
=========
Thinks like that:

```bash
if [ $(echo "$(curl https://api.github.com/repos/mongodb/mongo | grep -o '"watchers_count": [0-9]*' | awk '{print $2}')<2*$(curl https://api.github.com/repos/rethinkdb/rethinkdb | grep -o '"watchers_count": [0-9]*' | awk '{print $2}')" | bc -l) -eq 1 ]; then echo "Cake time ^_^"; else echo "Soon... The cake will come."; fi
```

What's in the box?
=========
`crawler.py` is going to do fetch data every hour from the Github api.
It just retrieve the number of stars of a list of repositories and store it in RethinkDB.
