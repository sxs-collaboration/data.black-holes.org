c = get_config()

c.Authenticator.whitelist = {'moble'}

from oauthenticator.github import LocalGitHubOAuthenticator
c.JupyterHub.authenticator_class = LocalGitHubOAuthenticator
c.LocalGitHubOAuthenticator.username_map = {
    'moble': 'boyle',
}
c.LocalGitHubOAuthenticator.oauth_callback_url = 'https://www.black-holes.org/jupyter/hub/oauth_callback'
with open('/run/secrets/client_id', 'r') as f:
    c.LocalGitHubOAuthenticator.client_id = f.read().strip()
with open('/run/secrets/client_secret', 'r') as f:
    c.LocalGitHubOAuthenticator.client_secret = f.read().strip()

c.JupyterHub.base_url = '/jupyter'

# c.Spawner.cmd = ['/opt/conda/bin/jupyterhub-singleuser']

c.JupyterHub.spawner_class='sudospawner.SudoSpawner'
c.Spawner.cmd = '/opt/conda/bin/sudospawner'
