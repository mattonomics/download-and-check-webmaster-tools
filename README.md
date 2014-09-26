Download and Check Crawl Errors
==================================

A tool for WP-CLI that downloads your 404s and checks them.

### Get Sites For Your Email

`wp gtools webmaster get-sites --email=youremail@domain.com --password=yourpassword`


### Download All Crawl Errors For A Site

`wp gtools webmaster download-404s --email=youremail@domain.com --password=yourpassword --account=http://www.site.com/`

Note that the URL in the account flag `--account=http://www.site.com/` must match *identically* to the site output in `get-sites`.


### Check Downloaded File & Create A Results CSV File

Once you have downloaded the crawl errors and implemented fixes on your site, you should check them.

`wp gtools webmaster check --email=youremail@domain.com --password=yourpassword`

If you have a mirror of the site locally, you may want to swap the URL in your crawl error file. To do so, use the following:

`wp gtools webmaster check --new-url=http://www.site.dev/ --email=youremail@domain.com --password=yourpassword`