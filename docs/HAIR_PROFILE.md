# Hair profile and concern interpretation

Customers can save and update their own structured profile at /assessment. Scalp type and hair-length condition are separate. Optional English/Taglish text is limited to 500 Unicode characters and 2,000 bytes; customers are asked to omit identifying and medical information.

PHP validates the POST, enforces customer eligibility and CSRF, invokes a local Python process, and commits the profile, canonical tags and a text-free audit together. Prepared statements and encoded output protect storage/display. A content fingerprint rejects stale forms. Existing hair_profiles and hair_profile_tags tables are reused; no migration is needed for this increment.

Set NLP_PYTHON_BINARY privately to a working Python 3.10+ executable. No Python packages or network service are needed. PHP must permit proc_open; POSIX hosts also need posix_kill for process-group cleanup. The interpreter receives bounded JSON over stdin and uses a fixed local runner without a shell. PHP polls process status and output size, enforcing the three-second execution deadline with at most a half-second cleanup polling allowance. Windows requires job-object containment; failure to establish it fails closed. The invocation's interpreter and descendants are terminated together, without process-name searches.

Windows anonymous pipe reads do not support the required nonblocking behavior. Request input/output therefore uses temporary file handles, automatically removed on close; stderr is discarded. Configure PHP's temporary directory outside the public document root with access restricted to the service account. These are transient request data, not application logs. The 500-character/2,000-byte input and 32,768-byte output bounds, JSON validation and text-free auditing remain in effect. If interpretation fails or times out, the profile is not saved; the customer can retry or leave optional text blank.

nlp/concerns.json contains versioned phrase/synonym rules. nlp/interpret.py performs Unicode normalization, longest-phrase matching, local negation detection, scalp/length separation and conservative conflict removal. It returns canonical tags, matched phrases, rule IDs and fallback warnings. Structured scalp/texture answers take precedence. This is deterministic lightweight NLP, not trained AI or clinical classification. It cannot understand every sentence, slang expression or complex negation.

The optional original description is stored only in the customer's profile, not in audit entries. Interpretations can be inspected on the saved profile. Account ownership is always taken from the authenticated session; submitted user/profile IDs are not trusted.

Run python -I -B tests/concerns.py, php tests/hair_validation.php and php tests/nlp_process.php (with NLP_PYTHON_BINARY configured). The process suite uses synthetic fixtures and checks real deadlines, hung/child cleanup, malformed responses, Unicode, paths with spaces and concurrency. HTTP/database tests require a disposable instance and are separately guarded against the personal development ports.

This implements the proposal's structured assessment, optional concern interpretation and saved-profile scope (HP01–HP03), with owner isolation and secure form handling (RB02, SE02, SE04). Product matching and saved routine generation are separate increments.
