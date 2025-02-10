&nbsp;
<p align="center">
    <img src="https://github.com/mosparo/mosparo/blob/master/assets/images/mosparo-logo.svg?raw=true" alt="mosparo logo contains a bird with the name Mo and the mosparo text"/>
</p>

<h1 align="center">
    Rule Package Generator (RPG)
</h1>
<p align="center">
    With the Rule Package Generator, you can convert simple lists into a rule package and use it with mosparo.
</p>

-----

## Description

Creating a rule package for mosparo is easy but requires some programming work. With the mosparo Rule Package Generator (RPG), no programming work is needed to create a new rule package.

You can define which kind of input you want to give into the RPG. You can use a simple list of items (one item per line) or a CSV file with the subtype, the value, and the rating all in one file (more inputs will follow). The RPG will then automatically organize the source data, convert it to the rule package format, and store the whole rule package in the defined output.

You can define as many inputs and outputs in a profile as you want.

## Inspired by

This tool was born in a discussion about rule packages, initially started by [StrangerGithuber](https://github.com/StrangerGithuber) and supported by [Digi92](https://github.com/Digi92).

## Installation

_To be defined_

### Build from source

You can run this tool by some simple steps:

1. Clone the repository
2. Install the dependencies
```commandline
composer install
```
3. Use it
```commandline
./bin/console rpg:generate-rule-package <PATH_TO_PROFILE_FILE.yaml>
```

## Profile file

You need a profile, which is the configuration for a rule package and stored in a YAML file, to generate a rule package.

### Example

This simple example profile will take the list from `input/test-list.txt` and build a word rule with the items in that list. The generated rule package is stored in the file `output/test-rule-package.json`. This file can then be hosted on a web server and used with mosparo.

```yaml
version: 1.0
name: Test rule package
refresh_interval: 3600
uuid_index_path: ~
abort_on_error:

input:
  - type: list
    source:
      type: file
      path: ./input/test-list.txt
    rule:
      identifier: local_test_list
      name: Blocked words from local list
      type: word
      item:
        type: word
        rating: 2

output:
  - type: file
    identifier: store_in_file
    options:
      file_path: ./output/test-rule-package.json

```

## Available options

### Input

#### `type`

| Type    | Description                                           |
|---------|-------------------------------------------------------|
| `list`  | A list of items in a file with one item per row.      |
| `table` | A table of items in a CSV file with one item per row. |

#### `source` / `type`

| Source type | Description                                |
|-------------|--------------------------------------------|
| `file`      | The path to the file. Set property `path`. |
| `url`       | A URL to the file. Set property `url`.     |

### Output

#### `type`

Only `file` is available right now. More are planned.

## Todo

(In random order)

- [ ] Add better error handling
- [ ] Add profile validation
- [ ] Add tool to generate profile file
- [ ] Add option to store the rule package via API in mosparo (with mosparo v1.4)
- [ ] Add input type `external_list` and `external_table`
- [ ] Add input type `custom` (custom command to generate a rule)
- [ ] Add output type `custom` (custom command to store the rule package)
- [ ] Add better documentation
- [ ] Add automatic testing
- [ ] Add binaries/Docker image
- [ ] Adjust setup documentation

We're looking forward to your suggestions and bug reports.