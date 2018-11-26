# PARAMETERS

 * `source_calendar_filename` - source docx file (can local file name or remote url, supported by curl; `"./input/calendar_2019_0.docx"` by default)
 * `calendar_year` - force source calendar year (by default it is parsed from `source_calendar_filename`)
 * `output_json_filename` - output json file (`"./output/calendar_2019.json"` by default)

# HOW TO RUN from terminal
	php-cgi -f ./docx2json.php source_calendar_filename=./input/calendar_2019_0.docx output_json_filename=./output/calendar_2019.json

# HOW TO RUN from browser
	http://your.server:port/path/docx2json.php?source_calendar_filename=./input/calendar_2019_0.docx&output_json_filename=./output/calendar_2019.json

# INFLUENCED BY
 * https://github.com/kaleguy/docx2json (MIT)
 * https://github.com/matb33/docx2md (MIT)

# LICENSE
 MIT
