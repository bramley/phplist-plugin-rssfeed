# RSS Feed Plugin #

## Description ##

This plugin lets you create campaigns that include content from RSS or Atom feeds and which are automatically repeated at regular intervals.

The plugin adds a tab to the Send a campaign page on which you specify the URL of the RSS feed.
When the campaign is sent the content of the message has recent items, dependent on the repeat frequency, from the feed.


## Installation ##

### Dependencies ###

These dependencies must be met for the plugin to be installed and run successfully.

* phplist version 3.3.2 or later.
* php version 5.4 or later.
* The Common Plugin version 3.9.3 or later. You should install, or upgrade to, the latest version. See <https://github.com/bramley/phplist-plugin-common>

The plugin uses the PicoFeed package that additionally requires these php extensions to be loaded:

* iconv
* dom
* xml
* SimpleXML
* Multibyte String

### Set the plugin directory ###
The default plugin directory is `plugins` within the admin directory.

You can use a directory outside of the web root by changing the definition of `PLUGIN_ROOTDIR` in config.php.
The benefit of this is that plugins will not be affected when you upgrade phplist.

### Install through phplist ###
The recommended method of installing the plugin is through phplist.

Install on the Plugins page (menu Config > Plugins) using the package URL `https://github.com/bramley/phplist-plugin-rssfeed/archive/master.zip`

### Install manually ###
If you are not able to install the plugin through phplist then it can be installed manually instead.

Download the plugin zip file from <https://github.com/bramley/phplist-plugin-rssfeed/archive/master.zip>

Expand the zip file, then copy the contents of the plugins directory to your phplist plugins directory.
This should contain

* the file RssFeedPlugin.php
* the directory RssFeedPlugin

## Usage ##

For guidance on using the plugin and its configuration settings see the plugin's page within the phplist documentation site
<https://resources.phplist.com/plugin/rssfeed>

## Support ##

Please raise any questions or problems in the user forum <https://discuss.phplist.org/>.

## Donation ##
This plugin is free but if you install and find it useful then a donation to support further development is greatly appreciated.

[![Donate](https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=W5GLX53WDM7T4)

## Version history ##

    version     Description
    2.9.1+20210116  Github #24, #25
    2.9.0+20210113  Add config settings to enable/disable picofeed content filtering and content generating
    2.8.2+20200413  On the RSS Ffeds page show the number of active campaigns and sent campaigns
    2.8.1+20200328  On RSS feeds page display active feeds first
    2.8.0+20200305  Support getting an attribute value of a custom element
    2.7.2+20190521  Improve the selection of feed items for a test message
    2.7.1+20190514  Add validation of repeat interval and DEFAULT_MESSAGEAGE
    2.7.0+20181210  Improve methods of running get and delete by cron jobs
    2.6.2+20181126  Fix bug of missing call to parent method introduced in 2.6.0
    2.6.1+20181116  Allow the get page to be accessed using the remote processing secret
    2.6.0+20181115  New page to display all feeds
    2.5.7+20181013  Allow the display format of the published date to be configurable
    2.5.6+20180517  Remove dependency on php 5.6
    2.5.5+20180423  Reduce level of error reporting
    2.5.4+20180224  Minor code change
    2.5.3+20180111  Minor bug fix
    2.5.2+20180102  Handle emoji characters in feed html
    2.5.1+20171204  Remove debug logging
    2.5.0+20171204  Lots of internal reworking
    2.4.4+20170402  Add PicoFeed package
    2.4.3+20170209  Added hook for copying a campaign
    2.4.2+20161227  Allow RSS placeholder to be in the template or the message content
    2.4.1+20161013  Move System menu items to Campaigns menu
    2.4.0+20160824  Support custom tags
    2.3.2+20160419  Rework parsing of twitter timeline
    2.3.1+20160105  Fix output buffering problem
    2.3.0+20151231  Add public page to generate RSS from a twitter timeline
    2.2.1+20151124  Use description element as content for an RSS feed
    2.2.0+20151121  Support for View in Browser plugin, reposition RSS tab
    2.1.2+20151014  GitHub #8, #9, #10
    2.1.1+20150828  Update to dependencies
    2.1.0+20150812  Use title of latest item in subject, use feed items for test message
                    Display items in ascending or descending order of published date
    2015-08-01      Display feed url on view message page, add description to plugin page
    2015-07-14      Fix for GitHub #7
    2015-07-12      Display items in reverse chronological order
                    GitHub #4, #5, #6
    2015-05-10      Add dependency checks
    2015-04-11      Improve timezone handling for published datetime
                    Fetch items only for active feeds
    2015-04-08      Corrected error reporting
    2015-04-04      Internal changes
    2015-04-01      Generate items as plain text in addition to html
                    Include sample items when sending a test message
    2015-03-29      Use embargo instead of now() to select recent items
                    A message that does not have any content now has embargo moved forward
    2015-03-24      Release to GitHub
