# $Id: perl-MIME-Lite.spec 7743 2009-09-08 15:45:40Z cmr $
# Authority: dries
# Upstream: Ricardo SIGNES <rjbs$cpan,org>

%define perl_vendorlib %(eval "`%{__perl} -V:installvendorlib`"; echo $installvendorlib)
%define perl_vendorarch %(eval "`%{__perl} -V:installvendorarch`"; echo $installvendorarch)

%define real_name MIME-Lite

Summary: Simple standalone module for generating MIME messages
Name: perl-MIME-Lite
Version: 3.025
Release: 1.rf
License: Artistic/GPL
Group: Applications/CPAN
URL: http://search.cpan.org/dist/MIME-Lite/

Packager: Dries Verachtert <dries@ulyssis.org>
Vendor: Dag Apt Repository, http://dag.wieers.com/apt/

Source: http://www.cpan.org/modules/by-module/MIME/MIME-Lite-%{version}.tar.gz
BuildRoot: %{_tmppath}/%{name}-%{version}-%{release}-root

BuildArch: noarch
BuildRequires: perl
BuildRequires: perl(ExtUtils::MakeMaker)
BuildRequires: perl(File::Basename)
BuildRequires: perl(MIME::Base64)
BuildRequires: perl(MIME::Types)
BuildRequires: perl(MIME::QuotedPrint)
BuildRequires: perl(Mail::Address)
Requires: perl(Email::Date::Format)

%description
MIME-Lite is a simple standalone module for generating MIME messages.

%prep
%setup -n %{real_name}-%{version}

%build
echo | %{__perl} Makefile.PL INSTALLDIRS="vendor" PREFIX="%{buildroot}%{_prefix}"
%{__make} %{?_smp_mflags}

%install
%{__rm} -rf %{buildroot}
%{__make} pure_install

### Clean up buildroot
find %{buildroot} -name .packlist -exec %{__rm} {} \;

### Clean up docs
find contrib/ examples/ -type f -exec %{__chmod} a-x {} \;

%clean
%{__rm} -rf %{buildroot}

%files
%defattr(-, root, root, 0755)
%doc COPYING INSTALLING LICENSE MANIFEST META.yml README changes.pod contrib/ examples/
%doc %{_mandir}/man3/MIME::Lite.3pm*
%doc %{_mandir}/man3/MIME::changes.3pm*
%dir %{perl_vendorlib}/MIME/
#%{perl_vendorlib}/MIME/Lite/
%{perl_vendorlib}/MIME/Lite.pm
%{perl_vendorlib}/MIME/changes.pod

%changelog
* Tue Sep  8 2009 Christoph Maser <cmr@financial.com> - 3.025-1 - 7743/cmr
- Updated to version 3.025.

* Sat Jul  4 2009 Christoph Maser <cmr@financial.com> - 3.024-1
- Updated to version 3.024.

* Fri Jan 16 2009 Christoph Maser <cmr@financial.com> - 3.023-1
- Updated to release 3.023.
- Add dependency for perl(Email::Date::Format)

* Wed Dec 05 2007 Dag Wieers <dag@wieers.com> - 3.021-1
- Updated to release 3.021.

* Tue Nov 13 2007 Dag Wieers <dag@wieers.com> - 3.020-1
- Updated to release 3.020.

* Sat Nov 05 2005 Dries Verachtert <dries@ulyssis.org> 3.01-2
- URL changed to cpan.

* Sat Nov 05 2005 Dries Verachtert <dries@ulyssis.org> 3.01-1
- Updated to release 3.01.

* Sun Dec 11 2004 Dries Verachtert <dries@ulyssis.org> 2.117-2
- cleanup of spec file

* Fri Dec 26 2003 Dries Verachtert <dries@ulyssis.org> 2.117-1
- first packaging for Fedora Core 1
