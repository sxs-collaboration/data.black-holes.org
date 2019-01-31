require "jekyll-import";

JekyllImport::Importers::Joomla.run({
    "dbname"   => "joomla",
    # "user"     => "joomla_readwrite",
    "user"     => "joomla_readonly",
    "password" => File.read("/run/secrets/mdp"),
    "host"     => "joomladb",
    # "port"     => 3306,
    "section"  => "0",
    "prefix"   => "tjpa_"
})
