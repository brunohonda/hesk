# Hesk

This project create a container to Hesk project

[Hesk](https://www.hesk.com)
[Docker Hub Repository](https://hub.docker.com/repository/docker/brunoah/hesk)

## Initialize the container

```shellscript
docker run --name hesk -d --link mysql-container-name:mysql -p 80:80 brunoah/hesk
```

Where 'mysql-container-name' is the name of the container that is running the database that will be used.

## After startup

Go to http://<your-host>/install to initialize the installation

Where you-host is the server where you are running the Hesk container

## After install

To remove install folder run:

``` shellscript
docker exec -i hesk rm -R install
```
