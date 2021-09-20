<?php

namespace Jkribeiro;

use Exception;
use Github\Client;
use Github\ResultPager;
use Symfony\Component\Yaml\Yaml;

/**
 * Class GhUpdateSecretsVisibility.
 */
class GhUpdateSecretsVisibility
{
    /**
     * The GitHub personal access token with admin:org scope for API calls.
     *
     * @var string
     */
    protected $pat;

    /**
     * The GitHub organization.
     *
     * @var string
     */
    protected $org;

    /**
     * The config array.
     *
     * @var array
     */
    protected $config;

    /**
     * The secrets array.
     *
     * @var array
     */
    protected $secrets;

    /**
     * The repositories list.
     *
     * @var array
     */
    protected $repos;

    /**
     * The GitHub Client.
     *
     * @var \Github\Client
     */
    protected $client;

    /**
     * Constructor.
     *
     * @param string $config_file
     *   The path to the config file.
     *
     * @throws Exception
     */
    function __construct($config_file)
    {
        $config = Yaml::parseFile($config_file);

        $required_configs = [
            'org',
            'pat',
            'secrets',
            'repos_file',
        ];
        foreach ($required_configs as $config_item) {
            if (empty($config[$config_item])) {
                throw new Exception("The config $config_item is missing.");
            }
        }

        $this->config = $config;
        $this->pat = $config['pat'];
        $this->org = $config['org'];
        $this->secrets = $config['secrets'];
        $this->repos = array_map('trim', file($config['repos_file']));

        // Instantiate GH client.
        $client = new Client();
        $client->authenticate($this->pat, null, Client::AUTH_ACCESS_TOKEN);
        $this->client = $client;
    }

    /**
     * Returns the list of IDs from the repos listed in the file.
     */
    public function getRepoIds()
    {
        $ids = [];
        foreach ($this->repos as $repo_name) {
            try {
                $repo = $this->client->api('repo')->show($this->org, $repo_name);
            }
            catch (Exception $e) {
                print "[WARNING] Unable to get the ID of the repo '$repo_name'. Message: {$e->getMessage()}" . PHP_EOL;
                continue;
            }

            $ids[] = $repo['id'];
        }

        return $ids;
    }

    /**
     * Command entrypoint.
     */
    public function execute()
    {
        // Fetches repositories for organization.
        $repos_ids = $this->getRepoIds();

        foreach ($this->secrets as $secret_name) {
            print "[INFO] Updating $secret_name secret" . PHP_EOL;

            // List selected repositories for organization secret.
            $org_secrets_api = $this->client->organization()->secrets();
            $paginator = new ResultPager($this->client);
            $parameters = [
                $this->org,
                $secret_name,
            ];
            $first_page_result = $paginator->fetch($org_secrets_api, 'selectedRepositories', $parameters);

            // Collects the repo IDs from the first page.
            $existing_secret_repos_ids = [];
            foreach ($first_page_result['repositories'] as $secret_repo) {
                $existing_secret_repos_ids[] = $secret_repo['id'];
            }

            // Paginates the result, if it has pagination.
            while ($paginator->hasNext()) {
                $page_result = $paginator->fetchNext();

                foreach ($page_result['repositories'] as $secret_repo) {
                    $existing_secret_repos_ids[] = $secret_repo['id'];
                }
            }

            $existing_repos_count = count($existing_secret_repos_ids);
            print "[INFO] Currently there are $existing_repos_count selected repositories for $secret_name secret" . PHP_EOL;

            $diff_count = count(array_diff($repos_ids, $existing_secret_repos_ids));

            if ($diff_count === 0) {
                print "[INFO] All repositories listed in the repos file are already selected for the $secret_name secret; no actions needed" . PHP_EOL;
                continue;
            }

            print "[INFO] Selecting $diff_count new repositories for $secret_name secret" . PHP_EOL;

            // Update secret repo list.
            $this->client->organization()->secrets()->setSelectedRepositories($this->org, $secret_name, [
                'selected_repository_ids' => array_merge($existing_secret_repos_ids, $repos_ids),
            ]);

            print "[INFO] $diff_count new repositories were successfully selected for $secret_name secret" . PHP_EOL;
        }
    }
}
