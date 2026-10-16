# Hair profile and concern interpretation

Customers can save and update their own structured profile at /assessment. Scalp type and hair-length condition are separate. Optional English/Taglish text is limited to 500 Unicode characters and 2,000 bytes; customers are asked to omit identifying and medical information.

PHP validates the POST, enforces customer eligibility and CSRF, invokes a local Python process, and commits the profile, canonical tags and a text-free audit together. Prepared statements and encoded output protect storage/display. A content fingerprint rejects stale forms. Existing hair_profiles and hair_profile_tags tables are reused; no migration is needed for this increment.

Set NLP_PYTHON_BINARY privately to a working Python 3 executable. No Python packages or network service are needed. PHP must permit proc_open. The interpreter receives bounded JSON over stdin, uses a fixed script path without a shell, and has a three-second runtime/output limit. If unavailable, a nonempty description is not saved; the customer can retry or save structured choices with the optional text blank.

nlp/concerns.json contains versioned phrase/synonym rules. nlp/interpret.py performs Unicode normalization, longest-phrase matching, local negation detection, scalp/length separation and conservative conflict removal. It returns canonical tags, matched phrases, rule IDs and fallback warnings. Structured scalp/texture answers take precedence. This is deterministic lightweight NLP, not trained AI or clinical classification. It cannot understand every sentence, slang expression or complex negation.

The optional original description is stored only in the customer's profile, not in audit entries. Interpretations can be inspected on the saved profile. Account ownership is always taken from the authenticated session; submitted user/profile IDs are not trusted.

Run python -I -B tests/concerns.py and php tests/hair_validation.php (with NLP_PYTHON_BINARY configured). HTTP/database tests require a disposable instance and are separately guarded against the personal development ports.

This implements the proposal's structured assessment, optional concern interpretation and saved-profile scope (HP01–HP03), with owner isolation and secure form handling (RB02, SE02, SE04). Product matching and saved routine generation are separate increments.
