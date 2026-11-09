# Amendment conflict checker performance

The consultation setting is opt-in. Only users with the motion status-edit privilege can open or run the checker. A request compares at most 50 selected direct amendments to one motion. This bound follows benchmarks of the actual comparison service and results partial. Each pair is checked in both directions using the same word-level merge rules as amendment rewriting. A single request prepares each amendment diff once, then reuses it for pair comparisons.

To reproduce: install locked Composer dependencies, then run `php -d memory_limit=512M bin/benchmark-amendment-conflicts.php COUNT PARAGRAPHS sparse|dense 3`. The fixture changes one paragraph per amendment in sparse mode, or ten paragraphs in dense mode. The benchmark reports fixture construction, comparison, result rendering, response bytes, peak memory, and cold/warm timings separately. Run each workload in a new PHP process. It does not measure database access, HTTP transport, or browser rendering. The numbers below were measured in an isolated PHP 8.3.36 container on this workspace host; production hardware and text will vary.

| Amendments | Paragraphs | Edit pattern | Median total | Peak memory | Results HTML |
| ---: | ---: | --- | ---: | ---: | ---: |
| 25 | 200 | Sparse | 16 ms | 14 MiB | <1 MiB |
| 25 | 200 | Dense | 149 ms | 23 MiB | 3.6 MiB |
| 50 | 200 | Sparse | 29 ms | 18 MiB | <1 MiB |
| 50 | 200 | Dense | 436 ms | 55 MiB | 14.6 MiB |
| 100 | 200 | Dense | 1,519 ms | 172 MiB | 59.1 MiB |
| 200 | 20 | Sparse | 93 ms | 20 MiB | 1.5 MiB |

A 200-amendment dense run did not complete with a 512 MiB PHP memory limit while assembling results. Even 100 dense amendments produce a very large response. The 50-amendment selection limit prevents these observed cases; the setting and checker page advise using smaller groups when texts are long or conflicts are numerous. The limit constrains selected amendments per check, not the total number of amendments in a consultation.

A separate request check used the repository's `dbdata1.sql` fixture in MariaDB 11 and PHP's built-in web server in the same isolated Docker network. On motion A2, a public GET returned the selectable page; POST checks of two and seven amendments returned in 23–65 ms and 38–43 ms respectively, including database loading and the full layout. Forged amendment IDs returned HTTP 400. Turning the option off removed the sidebar link and returned HTTP 404 at the checker URL. This fixture is much smaller than the synthetic worst-case workload above.
