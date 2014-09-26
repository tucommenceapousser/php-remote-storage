FROM       fedora:20
#FROM       centos:centos7
MAINTAINER François Kooman <fkooman@tuxed.net>

# Add EPEL repository (CentOS 7)
#RUN yum -y install https://download.fedoraproject.org/pub/epel/7/x86_64/e/epel-release-7-1.noarch.rpm ; yum clean all

# Add php-oauth repository (Fedora, CentOS 6, CentOS 7)
RUN curl -s -L -o /etc/yum.repos.d/fedora-php-oauth.repo https://repos.fedorapeople.org/repos/fkooman/php-oauth/fedora-php-oauth.repo

# Perform updates
RUN yum -y update; yum clean all

# Install dependencies (and SSL module)
RUN yum install -y mod_ssl php-oauth-as php-simple-auth php-remote-storage; yum clean all

# Allow connections from everywhere
RUN sed -i 's/Require local/Require all granted/' /etc/httpd/conf.d/php-oauth-as.conf
RUN sed -i 's/Allow from 127.0.0.1/Allow from All/' /etc/httpd/conf.d/php-oauth-as.conf
RUN sed -i 's/Allow from ::1//' /etc/httpd/conf.d/php-oauth-as.conf

RUN sed -i 's/Require local/Require all granted/' /etc/httpd/conf.d/php-simple-auth.conf
RUN sed -i 's/Allow from 127.0.0.1/Allow from All/' /etc/httpd/conf.d/php-simple-auth.conf
RUN sed -i 's/Allow from ::1//' /etc/httpd/conf.d/php-simple-auth.conf

RUN sed -i 's/Require local/Require all granted/' /etc/httpd/conf.d/php-remote-storage.conf
RUN sed -i 's/Allow from 127.0.0.1/Allow from All/' /etc/httpd/conf.d/php-remote-storage.conf
RUN sed -i 's/Allow from ::1//' /etc/httpd/conf.d/php-remote-storage.conf

# Modify OAuth server configuration to allow dynamic client registration for
# remoteStorage clients
RUN sed -i 's/allowRemoteStorageClients = false/allowRemoteStorageClients = true/' /etc/php-oauth-as/oauth.ini

# Use the OAuth server introspect endpoint, as this is on the same machine
# non TLS is fine...
RUN sed -i 's|https://auth.example.org/introspect.php|http://localhost/php-oauth-as/introspect.php|' /etc/php-remote-storage/rs.ini

# Add users to php-simple-auth
RUN php-simple-auth-add-user admin adm1n
RUN php-simple-auth-add-user fkooman foobar

USER apache

# Init OAuth Database
RUN php-oauth-as-initdb

# Init remoteStorage Database
RUN php-remote-storage-initdb

# Register Default Clients
RUN curl -s -L -o /tmp/config.json https://www.php-oauth.net/app/config.json
RUN php-oauth-as-register /tmp/config.json

USER root

# Expose port 443 and set httpd as our entrypoint
EXPOSE 443
ENTRYPOINT ["/usr/sbin/httpd"]
CMD ["-D", "FOREGROUND"]
