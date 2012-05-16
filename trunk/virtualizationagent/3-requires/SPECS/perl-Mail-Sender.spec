# $Id: perl-Mail-Sender.spec 4245 2006-03-26 21:29:30Z dries $

# Authority: dries
# Upstream: Jan Krynicky (=Jan Krynicky) <Jenda$Krynicky,cz>


%define real_name Mail-Sender
%define perl_vendorlib %(eval "`perl -V:installvendorlib`"; echo $installvendorlib)
%define perl_vendorarch %(eval "`perl -V:installvendorarch`"; echo $installvendorarch)
%define perl_archlib %(eval "`perl -V:archlib`"; echo $archlib)
%define perl_privlib %(eval "`perl -V:privlib`"; echo $privlib)

Summary: Module for sending mails with attachments
Name: perl-Mail-Sender
Version: 0.8.13
Release: 1.rf
License: Other
Group: Applications/CPAN
URL: http://search.cpan.org/dist/Mail-Sender/

Packager: Dries Verachtert <dries@ulyssis.org>
Vendor: Dag Apt Repository, http://dag.wieers.com/apt/

Source: http://www.cpan.org/modules/by-module/Mail/Mail-Sender-%{version}.tar.gz
BuildRoot: %{_tmppath}/%{name}-%{version}-%{release}-root

BuildArch: noarch
BuildRequires: perl
Requires: perl

%description
Mail::Sender is a module for sending mails with attachments through an SMTP
server.

%prep
%setup -n %{real_name}-%{version}

%build
%{__perl} Makefile.PL INSTALLDIRS="vendor" PREFIX="%{buildroot}%{_prefix}"
echo "N" | %{__make} %{?_smp_mflags} OPTIMIZE="%{optflags}"

%install
%{__rm} -rf %{buildroot}
%makeinstall
%{__rm} -f %{buildroot}%{perl_archlib}/perllocal.pod
%{__rm} -f %{buildroot}%{perl_vendorarch}/auto/*/*/.packlist
%{__rm} -f %{buildroot}%{perl_vendorlib}/Mail/Sender/CType/Win32.pm

%clean
%{__rm} -rf %{buildroot}

%files
%defattr(-, root, root, 0755)
%doc Changes README
%{_mandir}/man3/*
%{perl_vendorlib}/Mail/Sender.pm
%{perl_vendorlib}/Mail/Sender

%changelog
* Sun Mar 26 2006 Dries Verachtert <dries@ulyssis.org> - 0.8.13-1 - 4245/dries
- Updated to release 0.8.13.

* Wed Mar 22 2006 Dries Verachtert <dries@ulyssis.org> - 0.8.10-1.2
- Rebuild for Fedora Core 5.

* Sat Jun 5 2004 Dries Verachtert <dries@ulyssis.org> - 0.8.10-1
- Initial package.
