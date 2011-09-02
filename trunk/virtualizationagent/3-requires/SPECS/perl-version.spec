# $Id: perl-version.spec 6874 2009-06-08 15:30:21Z cmr $
# Authority: dag
# Upstream: John Peacock <jpeacock$rowman,com>

%define perl_vendorlib %(eval "`%{__perl} -V:installvendorlib`"; echo $installvendorlib)
%define perl_vendorarch %(eval "`%{__perl} -V:installvendorarch`"; echo $installvendorarch)

%define real_name version

Summary: Perl module that implements for Version Objects
Name: perl-version
Version: 0.76
Release: 1.rf
License: Artistic/GPL
Group: Applications/CPAN
URL: http://search.cpan.org/dist/version/

Packager: Dag Wieers <dag@wieers.com>
Vendor: Dag Apt Repository, http://dag.wieers.com/apt/

Source: http://www.cpan.org/modules/by-module/version/version-%{version}.tar.gz
BuildRoot: %{_tmppath}/%{name}-%{version}-%{release}-root

BuildRequires: perl >= 0:5.005
BuildRequires: perl(ExtUtils::MakeMaker)
Requires: perl >= 0:5.005

%description
version is a Perl module that implements for Version Objects.

%prep
%setup -n %{real_name}-%{version}

%build
CFLAGS="%{optflags}" %{__perl} Makefile.PL INSTALLDIRS="vendor" PREFIX="%{buildroot}%{_prefix}"
%{__make} %{?_smp_mflags} OPTIMIZE="%{optflags}"

%install
%{__rm} -rf %{buildroot}
%{__make} pure_install

### Clean up buildroot
find %{buildroot} -name .packlist -exec %{__rm} {} \;

%clean
%{__rm} -rf %{buildroot}

%files
%defattr(-, root, root, 0755)
%doc Changes MANIFEST MANIFEST.SKIP META.yml README
%doc %{_mandir}/man3/version.3pm*
%{perl_vendorarch}/auto/version/
%{perl_vendorarch}/version/
%{perl_vendorarch}/version.pm
%{perl_vendorarch}/version.pod

%changelog
* Mon Jun  8 2009 Christoph Maser <cmr@financial.com> - 0.76-1 - 6874/cmr
- Updated to version 0.76.

* Mon Nov 19 2007 Dag Wieers <dag@wieers.com> - 0.74-1
- Updated to release 0.74.

* Fri May 04 2007 Dag Wieers <dag@wieers.com> - 0.72.3-1
- Initial package. (using DAR)
