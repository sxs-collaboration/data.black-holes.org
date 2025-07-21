This repo contains the pages served as the `data.black-holes.org` site.

All the pages under `document_root` are served by github.  The marimo
notebook directory is converted to static HTML in a github action and
inserted into the `document_root/simulations` directory.


# DNS configuration

This just required a CNAME record pointing `data` to the value
`sxs-collaboration.github.io`.
