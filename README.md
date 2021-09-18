# GitHub update secrets visibility

PHP helper script to include new repositories for an organization secret when the visibility for repository access is set to `selected`.

GitHub API [related docs](https://docs.github.com/en/rest/reference/actions#set-selected-repositories-for-an-organization-secret). 

## Usage
Run `composer install`

Create a config file `config.yml` in the root folder with the following format:
```
org: <GitHub Organization>
pat: '<PAT token with admin:org AND repo full scope>'
secrets:
  - <secret name 1>
  - <secret name 2>
  - <secret name n...>
repos_file: <Path to the repositories text file>
```

Create a text file containing a list of repositories to be included in the secrets list.

Run the command to execute the script: `./run`
