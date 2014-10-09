%global github_owner     fkooman
%global github_name      php-remote-storage

Name:       php-remote-storage
Version:    0.1.5
Release:    1%{?dist}
Summary:    remoteStorage server written in PHP

Group:      Applications/Internet
License:    AGPLv3+
URL:        https://github.com/%{github_owner}/%{github_name}
Source0:    https://github.com/%{github_owner}/%{github_name}/archive/%{version}.tar.gz
Source1:    php-remote-storage-httpd-conf
Source2:    php-remote-storage-autoload.php

BuildArch:  noarch

Requires:   php >= 5.3.3
Requires:   php-openssl
Requires:   php-pdo
Requires:   httpd

Requires:   php-composer(fkooman/json) >= 0.5.1
Requires:   php-composer(fkooman/json) < 0.6.0
Requires:   php-composer(fkooman/config) >= 0.3.3
Requires:   php-composer(fkooman/config) < 0.4.0
Requires:   php-composer(fkooman/rest) >= 0.4.11
Requires:   php-composer(fkooman/rest) < 0.5.0
Requires:   php-composer(fkooman/oauth-common) >= 0.5.0
Requires:   php-composer(fkooman/oauth-common) < 0.6.0
Requires:   php-composer(fkooman/oauth-rs) >= 0.7.1
Requires:   php-composer(fkooman/oauth-rs) < 0.8.0

#Starting F21 we can use the composer dependency for Symfony
#Requires:   php-composer(symfony/classloader) >= 2.3.9
#Requires:   php-composer(symfony/classloader) < 3.0
Requires:   php-pear(pear.symfony.com/ClassLoader) >= 2.3.9
Requires:   php-pear(pear.symfony.com/ClassLoader) < 3.0
Requires:   php-pear(pear.symfony.com/Yaml) >= 2.3.9
Requires:   php-pear(pear.symfony.com/Yaml) < 3.0
Requires:   php-pear(pear.symfony.com/EventDispatcher) >= 2.3.9
Requires:   php-pear(pear.symfony.com/EventDispatcher) < 3.0

Requires:   php-pear(guzzlephp.org/pear/Guzzle) >= 3.9.2
Requires:   php-pear(guzzlephp.org/pear/Guzzle) < 4.0

Requires(post): policycoreutils-python
Requires(postun): policycoreutils-python

%description
This is a remoteStorage server implementation written in PHP. It aims at 
implementing draft-dejong-remotestorage-03.txt.

%prep
%setup -qn %{github_name}-%{version}

sed -i "s|dirname(__DIR__)|'%{_datadir}/php-remote-storage'|" bin/php-remote-storage-initdb

%build

%install
# Apache configuration
install -m 0644 -D -p %{SOURCE1} ${RPM_BUILD_ROOT}%{_sysconfdir}/httpd/conf.d/php-remote-storage.conf

# Application
mkdir -p ${RPM_BUILD_ROOT}%{_datadir}/php-remote-storage
cp -pr web src ${RPM_BUILD_ROOT}%{_datadir}/php-remote-storage

# use our own class loader
mkdir -p ${RPM_BUILD_ROOT}%{_datadir}/php-remote-storage/vendor
cp -pr %{SOURCE2} ${RPM_BUILD_ROOT}%{_datadir}/php-remote-storage/vendor/autoload.php

mkdir -p ${RPM_BUILD_ROOT}%{_bindir}
cp -pr bin/* ${RPM_BUILD_ROOT}%{_bindir}

# Config
mkdir -p ${RPM_BUILD_ROOT}%{_sysconfdir}/php-remote-storage
cp -p config/rs.ini.defaults ${RPM_BUILD_ROOT}%{_sysconfdir}/php-remote-storage/rs.ini
ln -s ../../../etc/php-remote-storage ${RPM_BUILD_ROOT}%{_datadir}/php-remote-storage/config

# Data
mkdir -p ${RPM_BUILD_ROOT}%{_localstatedir}/lib/php-remote-storage
mkdir -p ${RPM_BUILD_ROOT}%{_localstatedir}/lib/php-remote-storage/storage

%post
semanage fcontext -a -t httpd_sys_rw_content_t '%{_localstatedir}/lib/php-remote-storage(/.*)?' 2>/dev/null || :
restorecon -R %{_localstatedir}/lib/php-remote-storage || :

%postun
if [ $1 -eq 0 ] ; then  # final removal
semanage fcontext -d -t httpd_sys_rw_content_t '%{_localstatedir}/lib/php-remote-storage(/.*)?' 2>/dev/null || :
fi

%files
%defattr(-,root,root,-)
%config(noreplace) %{_sysconfdir}/httpd/conf.d/php-remote-storage.conf
%config(noreplace) %{_sysconfdir}/php-remote-storage
%{_bindir}/php-remote-storage-initdb
%dir %{_datadir}/php-remote-storage
%{_datadir}/php-remote-storage/src
%{_datadir}/php-remote-storage/vendor
%{_datadir}/php-remote-storage/web
%{_datadir}/php-remote-storage/config
%dir %attr(0700,apache,apache) %{_localstatedir}/lib/php-remote-storage
%dir %attr(0700,apache,apache) %{_localstatedir}/lib/php-remote-storage/storage

%doc README.md agpl-3.0.txt composer.json docs/ config/

%changelog
* Mon Oct 06 2014 François Kooman <fkooman@tuxed.net> - 0.1.5-1
- update to 0.1.5

* Thu Sep 25 2014 François Kooman <fkooman@tuxed.net> - 0.1.4-1
- update to 0.1.4

* Thu Sep 25 2014 François Kooman <fkooman@tuxed.net> - 0.1.3-1
- update to 0.1.3

* Wed Sep 24 2014 François Kooman <fkooman@tuxed.net> - 0.1.2-2
- also install the storage directory

* Wed Sep 24 2014 François Kooman <fkooman@tuxed.net> - 0.1.2-1
- update to 0.1.2

* Wed Sep 24 2014 François Kooman <fkooman@tuxed.net> - 0.1.1-1
- update to 0.1.1

* Wed Sep 24 2014 François Kooman <fkooman@tuxed.net> - 0.1.0-2
- include storage directory as well when creating folder

* Sun Sep 14 2014 François Kooman <fkooman@tuxed.net> - 0.1.0-1
- initial package
