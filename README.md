# portfolio-site

The source code of my (Wouter Gritter's) personal portfolio site. It's a custom site written without any frameworks, which
is subject to change. Currently it consists of a couple PHP scripts to build the HTML template and convert Markdown files
to HTML using [Parsedown](https://parsedown.org/). These Markdown files are converted on every request, which is quite
inefficient but an optimization I'll work on in the future. The site runs in Docker, so hopefully any security issues are
limited to a DOS attack on the site, rather than compromising the webserver.

See the active website on [gritter.nl](https://gritter.nl/).
