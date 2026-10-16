# Image-upload controls

Product and profile images require genuine PHP uploads, actual nonempty byte sizes up to 2 MB, an allowed JPEG/PNG/WebP extension, matching fileinfo MIME and image-header MIME, and random server-generated filenames. Profile photos additionally allow at most 6000 pixels on either side and 16 megapixels. These checks do not prove full raster decoding or absence of malware.

Both upload directories have tracked Apache policies restricting public files to generated raster names, disabling listing/CGI and forcing the default handler. Uploads fail closed if their storage policy is missing. Confirm policy enforcement on the actual Apache host; PHP's local development server does not exercise Apache directives. Alternative hosts need an equivalent static-only configuration, and private files must remain outside their public document root.

Profile path updates and audit writes commit together. A failed database/audit operation removes the new upload; successful replacement retires only the previous generated photo. Legacy assets and paths outside the service's directory are preserved. Product-image sharing/reference protection remains unchanged.

No maintained application-connected antivirus scanner was available in the reviewed local environment. Windows Defender's presence is not proof of synchronous upload scanning. IAS C22 remains partial. Do not label an upload malware-free. Before adding a scanner, obtain permission for installation/maintenance, choose scan-before-publication/quarantine behavior, fail closed on scanner errors/timeouts/unavailable definitions, and verify the provider's safe test-file procedure in a disposable environment. No fake scan endpoint or success flag is implemented.
