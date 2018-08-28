FROM jupyter/scipy-notebook

USER root
RUN groupadd -g 1003 git && \
    groupadd -g 1002 web && \
    useradd -l -u 522 -g 1003 -s /bin/bash -G 1002 git && \
    useradd -l -u 503 -g 1002 -s /bin/bash -G 1003 web && \
    usermod -a -G git,web $NB_USER
USER $NB_UID

RUN pip install --upgrade pip && \
    conda update --yes --name base conda && \
    conda install --yes --channel moble scri && \
    pip install sxs && \
    /bin/rm -rf /home/$NB_USER/.cache && \
    fix-permissions $CONDA_DIR && \
    fix-permissions /home/$NB_USER
