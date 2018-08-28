FROM jupyter/scipy-notebook

RUN pip install --upgrade pip && \
    conda install --yes -c moble scri && \
    pip install oauthenticator sxs && \
    jupyter lab build -y && \
    /bin/rm -rf /home/$NB_USER/.cache && \
    fix-permissions $CONDA_DIR && \
    fix-permissions /home/$NB_USER

USER root
RUN groupadd -g 1003 git && \
    groupadd -g 1002 web && \
    groupadd -g 1006 boyle && \
    useradd -l -u 522 -g 1003 -s /bin/bash -G 1002 git && \
    useradd -l -u 503 -g 1002 -s /bin/bash -G 1003 web && \
    useradd -l -u 1004 -g 1006 -s /bin/bash -G 1002,1003 boyle
USER $NB_UID

COPY ./jupyterhub_config.py /jupyterhub_config.py
