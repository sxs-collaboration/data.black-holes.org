#! /usr/bin/python

from sxs.metadata import create_web_files

create_web_files(catalog_root_directory='/sxs-annex/SimulationAnnex.git',
                 relative_directory_path='../SimulationAnnex.git',
                 public_json_directory='/web/servers/data/document_root/waveforms',
                 public_links_directory='/web/servers/data/document_root/waveforms/files',
                 private_json_directory='/web/servers/www/wiki/document_root/data/media/website/data/waveforms')
