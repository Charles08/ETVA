# $Id: perl-MIME-tools.spec 5725 2007-08-13 18:52:57Z dries $
# Authority: dag

%{?dist: %{expand: %%define %dist 1}}

%define perl_vendorlib %(eval "`%{__perl} -V:installvendorlib`"; echo $installvendorlib)
%define perl_vendorarch %(eval "`%{__perl} -V:installvendorarch`"; echo $installvendorarch)

%define real_name MIME-tools

Summary: Perl modules for parsing (and creating!) MIME entities
Name: perl-MIME-tools
Version: 5.420
Release: 2.el5.rf
License: GPL
Group: Applications/CPAN
URL: http://search.cpan.org/dist/MIME-tools/

Source: http://www.cpan.org/modules/by-module/MIME/MIME-tools-%{version}.tar.gz
Packager: Dag Wieers <dag@wieers.com>
Vendor: Dries RPM Repository http://dries.ulyssis.org/rpm/
Patch: http://www.roaringpenguin.com/mimedefang/mime-tools-patch.txt
Patch1: MIME-Tools.diff
BuildRoot: %{_tmppath}/%{name}-%{version}-%{release}-root

BuildArch: noarch
BuildRequires: perl(IO::Stringy) >= 1.211, perl-MailTools, perl(ExtUtils::MakeMaker)
Requires: perl(IO::Stringy) >= 1.211, perl-MailTools >= 1.15
%{?rh7:BuildRequires: perl(MIME::Base64) >= 2.04}
%{?el2:BuildRequires: perl-MIME-Base64 >= 2.04}

%description
MIME-tools - modules for parsing (and creating!) MIME entities Modules in this
toolkit : Abstract message holder (file, scalar, etc.), OO interface for
decoding MIME messages, an extracted and decoded MIME entity, Mail::Field
subclasses for parsing fields, a parsed MIME header (Mail::Header subclass),
parser and tool for building your own MIME parser, and utilities.

%prep
%setup -n %{real_name}-%{version}
#patch -p1
#patch1 -p1

%build
%{__perl} Makefile.PL INSTALLDIRS="vendor" PREFIX="%{buildroot}%{_prefix}"
%{__make} %{?_smp_mflags}
#{__make} test

%install
%{__rm} -rf %{buildroot}
%{__make} pure_install

### Clean up buildroot
find %{buildroot} -name .packlist -exec %{__rm} {} \;

### Clean up docs
find examples/ -type f -exec %{__chmod} a-x {} \;

%clean
%{__rm} -rf %{buildroot}

%files
%defattr(-, root, root, 0755)
%doc ChangeLog COPYING INSTALLING MANIFEST README* examples/
%doc %{_mandir}/man3/*.3pm*
%{perl_vendorlib}/MIME/

%changelog
* Tue Aug 07 2007 Dag Wieers <dag@wieers.com> - 5.420-2 #5725
- Disabled auto-requires for examples/.

* Mon Apr 17 2006 Dag Wieers <dag@wieers.com> - 5.420-1
- Updated to release 5.420.

* Fri Jan 13 2006 Dag Wieers <dag@wieers.com> - 5.419-1
- Updated to release 5.419.

* Sun Dec 04 2005 Dries Verachtert <dries@ulyssis.org> - 5.418-2
- Rebuild.

* Sat Nov  5 2005 Dries Verachtert <dries@ulyssis.org> - 5.418-1
- Updated to release 5.418.

* Thu Mar 10 2005 Dag Wieers <dag@wieers.com> - 5.417-1
- Updated to release 5.417.

* Mon Dec 20 2004 Dag Wieers <dag@wieers.com> - 5.415-1
- Updated to release 5.415.

* Sun Jan 26 2003 Dag Wieers <dag@wieers.com>
- Initial package. (using DAR)
