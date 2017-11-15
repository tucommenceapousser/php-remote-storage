%global composer_vendor         fkooman
%global composer_project        php-remote-storage
%global composer_namespace      %{composer_vendor}/RemoteStorage

%global github_owner            fkooman
%global github_name             php-remote-storage
%global github_commit           29895304e98dba82d0a9a6ea0cf0e7148ec38ce6
%global github_short            %(c=%{github_commit}; echo ${c:0:7})

Name:       php-remote-storage
Version:    2.0.0
Release:    0.13%{?dist}
Summary:    remoteStorage server written in PHP

Group:      Applications/Internet
License:    AGPLv3+

URL:        https://github.com/%{github_owner}/%{github_name}
Source0:    %{url}/archive/%{github_commit}/%{name}-%{version}-%{github_short}.tar.gz

BuildArch:  noarch
BuildRoot:  %{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n) 

#        "php": ">=5.4",
BuildRequires:  php(language) >= 5.4
#        "ext-date": "*",
#        "ext-filter": "*",
#        "ext-hash": "*",
#        "ext-json": "*",
#        "ext-mbstring": "*",
#        "ext-pcre": "*",
#        "ext-pdo": "*",
#        "ext-session": "*",
#        "ext-spl": "*",
BuildRequires:  php-date
BuildRequires:  php-filter
BuildRequires:  php-hash
BuildRequires:  php-json
BuildRequires:  php-mbstring
BuildRequires:  php-pcre
BuildRequires:  php-pdo
BuildRequires:  php-session
BuildRequires:  php-spl
#        "fkooman/secookie": "^2.0",
#        "paragonie/constant_time_encoding": "^1",
#        "paragonie/random_compat": "^1|^2",
#        "symfony/polyfill-php56": "^1",
#        "symfony/yaml": "^2.8",
#        "twig/twig": "^1"
BuildRequires:  php-composer(fkooman/secookie)
BuildRequires:  php-composer(paragonie/constant_time_encoding)
BuildRequires:  php-composer(paragonie/random_compat)
BuildRequires:  php-composer(symfony/polyfill-php56)
BuildRequires:  php-composer(symfony/yaml)
BuildRequires:  php-composer(twig/twig) < 2
BuildRequires:  php-composer(fedora/autoloader)
BuildRequires:  %{_bindir}/phpunit

#        "php": ">=5.4",
Requires:  php(language) >= 5.4
#        "ext-date": "*",
#        "ext-filter": "*",
#        "ext-hash": "*",
#        "ext-json": "*",
#        "ext-mbstring": "*",
#        "ext-pcre": "*",
#        "ext-pdo": "*",
#        "ext-session": "*",
#        "ext-spl": "*",
Requires:  php-date
Requires:  php-filter
Requires:  php-hash
Requires:  php-json
Requires:  php-mbstring
Requires:  php-pcre
Requires:  php-pdo
Requires:  php-session
Requires:  php-spl
#        "fkooman/secookie": "^2.0",
#        "paragonie/constant_time_encoding": "^1",
#        "paragonie/random_compat": "^1|^2",
#        "symfony/polyfill-php56": "^1",
#        "symfony/yaml": "^2.8",
#        "twig/twig": "^1"
Requires:  php-composer(fkooman/secookie)
Requires:  php-composer(paragonie/constant_time_encoding)
Requires:  php-composer(paragonie/random_compat)
Requires:  php-composer(symfony/polyfill-php56)
Requires:  php-composer(symfony/yaml)
Requires:  php-composer(twig/twig) < 2
Requires:  php-composer(fedora/autoloader)

Requires:   mod_security
Requires:   mod_xsendfile

%if 0%{?fedora} >= 24
Requires:   httpd-filesystem
%else
# EL7 does not have httpd-filesystem
Requires:   httpd
%endif

Requires(post): %{_sbindir}/semanage
Requires(postun): %{_sbindir}/semanage

%description
This is a remoteStorage server implementation written in PHP. It aims at 
implementing draft-dejong-remotestorage-03.txt and higher.

%prep
%setup -qn %{github_name}-%{github_commit} 

%build
cat <<'AUTOLOAD' | tee src/autoload.php
<?php
require_once '%{_datadir}/php/Fedora/Autoloader/autoload.php';

\Fedora\Autoloader\Autoload::addPsr4('fkooman\\RemoteStorage\\', __DIR__);
\Fedora\Autoloader\Dependencies::required(array(
    '%{_datadir}/php/fkooman/SeCookie/autoload.php',
    '%{_datadir}/php/ParagonIE/ConstantTime/autoload.php',
    '%{_datadir}/php/random_compat/autoload.php',
    '%{_datadir}/php/Symfony/Polyfill/autoload.php',
    '%{_datadir}/php/Symfony/Component/Yaml/autoload.php',
    '%{_datadir}/php/Twig/autoload.php',
));
AUTOLOAD

%install
mkdir -p %{buildroot}%{_datadir}/%{name}
cp -pr bin src web views %{buildroot}%{_datadir}/%{name}
mkdir -p %{buildroot}%{_bindir}
chmod +x %{buildroot}%{_datadir}/%{name}/bin/*
ln -s %{_datadir}/%{name}/bin/add-user.php %{buildroot}%{_bindir}/%{name}-add-user

mkdir -p %{buildroot}%{_sysconfdir}/httpd/conf.d/

cat << 'EOF' | tee %{buildroot}%{_sysconfdir}/httpd/conf.d/%{name}.conf
Alias /php-remote-storage /usr/share/php-remote-storage/web
#Alias /.well-known/webfinger /usr/share/php-remote-storage/web

<Directory /usr/share/php-remote-storage/web>
    Require all granted
    #Require local

    XSendFile on
    XSendFilePath /var/lib/php-remote-storage/storage

    # Limit the request body to 8M
    LimitRequestBody 8388608

    RewriteEngine on
    RewriteBase /php-remote-storage
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [L,QSA]
</Directory>
EOF

mkdir -p %{buildroot}%{_sysconfdir}/%{name}/default
cp -pr config/server.yaml.example %{buildroot}%{_sysconfdir}/%{name}/server.yaml
ln -s ../../../etc/%{name} %{buildroot}%{_datadir}/%{name}/config

mkdir -p %{buildroot}%{_localstatedir}/lib/%{name}
ln -s ../../../var/lib/%{name} %{buildroot}%{_datadir}/%{name}/data

%check
cat << 'EOF' | tee tests/autoload.php
<?php
require_once '%{_datadir}/php/Fedora/Autoloader/autoload.php';

\Fedora\Autoloader\Dependencies::required(array(
    '%{buildroot}/%{_datadir}/%{name}/src/autoload.php',
));
\Fedora\Autoloader\Autoload::addPsr4('fkooman\\RemoteStorage\\Tests\\', dirname(__DIR__) . '/tests');
EOF

%{_bindir}/phpunit --bootstrap=tests/autoload.php

%post
semanage fcontext -a -t httpd_sys_rw_content_t '%{_localstatedir}/lib/%{name}(/.*)?' 2>/dev/null || :
restorecon -R %{_localstatedir}/lib/%{name} || :
# remove template cache if it is there
rm -rf %{_localstatedir}/lib/%{name}/*/tpl/* >/dev/null 2>/dev/null || :

%postun
if [ $1 -eq 0 ] ; then  # final removal
semanage fcontext -d -t httpd_sys_rw_content_t '%{_localstatedir}/lib/%{name}(/.*)?' 2>/dev/null || :
fi
# remove template cache if it is there
rm -rf %{_localstatedir}/lib/%{name}/*/tpl/* >/dev/null 2>/dev/null || :

%files
%defattr(-,root,root,-)
%config(noreplace) %{_sysconfdir}/httpd/conf.d/%{name}.conf
%dir %attr(0750,root,apache) %{_sysconfdir}/%{name}
%dir %attr(0750,root,apache) %{_sysconfdir}/%{name}/default
%config(noreplace) %{_sysconfdir}/%{name}/server.yaml
%{_bindir}/*
%{_datadir}/%{name}
%dir %attr(0700,apache,apache) %{_localstatedir}/lib/%{name}
%doc README.md CHANGES.md HACKING.md DEVELOPMENT.md SERVER.md composer.json config contrib specification
%license LICENSE

%changelog
* Wed Nov 15 2017 François Kooman <fkooman@tuxed.net> - 2.0.0-0.13
- rebuilt

* Wed Nov 15 2017 François Kooman <fkooman@tuxed.net> - 2.0.0-0.12
- rebuilt

* Wed Feb 22 2017 François Kooman <fkooman@tuxed.net> - 2.0.0-0.11
- rebuilt

* Wed Feb 22 2017 François Kooman <fkooman@tuxed.net> - 2.0.0-0.10
- rebuilt

* Sun Feb 19 2017 François Kooman <fkooman@tuxed.net> - 2.0.0-0.9
- rebuilt

* Thu Feb 16 2017 François Kooman <fkooman@tuxed.net> - 2.0.0-0.8
- rebuilt

* Wed Feb 08 2017 François Kooman <fkooman@tuxed.net> - 2.0.0-0.7
- rebuilt

* Wed Feb 08 2017 François Kooman <fkooman@tuxed.net> - 2.0.0-0.6
- rebuilt

* Wed Feb 08 2017 François Kooman <fkooman@tuxed.net> - 2.0.0-0.5
- rebuilt

* Wed Feb 08 2017 François Kooman <fkooman@tuxed.net> - 2.0.0-0.4
- rebuilt

* Tue Jan 17 2017 François Kooman <fkooman@tuxed.net> - 2.0.0-0.3
- rebuilt

* Tue Jan 17 2017 François Kooman <fkooman@tuxed.net> - 2.0.0-0.2
- rebuilt

* Tue Jan 17 2017 François Kooman <fkooman@tuxed.net> - 2.0.0-0.1
- update to master branch for future 2.0.0 release

* Tue Nov 22 2016 François Kooman <fkooman@tuxed.net> - 1.0.5-1
- update to 1.0.5
- run tests unconditionally
- include vendor/ directory in package, dependencies are kind of a mess

* Sun Aug 07 2016 François Kooman <fkooman@tuxed.net> - 1.0.4-1
- update to 1.0.4

* Wed May 25 2016 François Kooman <fkooman@tuxed.net> - 1.0.3-1
- update to 1.0.3

* Thu Mar 31 2016 François Kooman <fkooman@tuxed.net> - 1.0.2-2
- remove the template cache on install/update

* Fri Mar 25 2016 François Kooman <fkooman@tuxed.net> - 1.0.2-1
- update to 1.0.2

* Thu Jan 07 2016 François Kooman <fkooman@tuxed.net> - 1.0.1-2
- COPR is confused about the tar format, hopefully bump will fix this

* Thu Jan 07 2016 François Kooman <fkooman@tuxed.net> - 1.0.1-1
- update to 1.0.1

* Sat Dec 19 2015 François Kooman <fkooman@tuxed.net> - 1.0.0-1
- initial release
