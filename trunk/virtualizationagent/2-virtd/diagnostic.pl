#!/usr/bin/perl
# Copywrite Eurotux 2012
# 
# MFD 2012/01/31 (mfd@eurotux.com)

=pod

=head1 NAME

diagnostic.pl - script that aggregates agent info

=head1 SYNOPSIS

    perl diagnostic.pl [/path/to/filename]

=head1 DESCRIPTION

...

=head1 METHODS

=over 4

=cut

use strict;
use ETVA::EmailLogs;
use Data::Dumper;
use VirtAgentInterface;
use Time::localtime;

=item main

main func

=over

=item *

    read params from @ARGV with format p1=v1, ..., pn=vn
    and split into %Hash with ETVA::Client->splitOps function

=item *

    receive response and testing for error message

=back

=cut

if((scalar @ARGV == 1 && $ARGV[0] == 'help') || (scalar @ARGV != 2 && scalar @ARGV != 0)){
    print "usage:\nperl -I/srv/etva-vdaemon/lib /srv/etva-vdaemon/diagnostic.pl [[agent ip addr] [agent port]]\n";
    exit -1;
}

my $output      = $ENV{'diagnostic_file'} || '/tmp/diagnostic_ball.tar';
my $conffile    = $ENV{'virtd_conf_file'} || "/etc/sysconfig/etva-vdaemon/virtd.conf";
my $rcptto      = $ENV{'diagnostic_rcpt'} || 'mfd@eurotux.com';#'etvm@suporte.eurotux.com';
my $smtp_server = $ENV{'smtp_server'}     || 'zeus.eurotux.com';
my $send_diagnostic = $ENV{'send_diagnostic'} || 0;
print "[INFO] ENV{send_diagnostic} = $send_diagnostic\n";

my ($ip, $port);
if(scalar @ARGV == 2){
    $ip = @ARGV[0];
    $port = @ARGV[1];
}else{
    $ip = &get_ip;
    $port = &get_port;
}
print "[INFO] Using $ip:$port\n";

&agentOffReport($output);

#if(&main($ip, $port)){
#    if($send_diagnostic){
#        if(ETVA::EmailLogs::send_diagnostic($rcptto, $smtp_server, $output)){
#            print "[INFO] The email was successfully sent to: $rcptto\n";
#            exit 0;
#        }else{
#            print STDERR $output;
#            exit -1;
#        }
#    }
#    print "[INFO] Everything went well!!!\n";
#    exit 0;
#}else{
#    print STDERR "[ERROR] Cannot get diagnostic\n";
#    exit 2;
#}

sub get_ip{
    my $ip = `cat $conffile | egrep '^LocalIP' | tr -d 'LocalIP = '`;

    if($ip =~ /^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/){
        chomp $ip;
        print "[INFO] Ip address found: $ip\n";
        return $ip;
    }else{
        print "[ERROR] Cannot retrieve ip address from Management interface\n";
        exit -2;
    }
}

sub get_port{
    my $port = `cat $conffile | egrep '^Port' | tr -d 'Port = '`;

    if($port =~ /^\d{1,5}$/){
        print "[INFO] Local agent port found: $port\n";
        return $port;
    }else{
        print "[ERROR] Cannot retrieve virtualization agent port\n";
        exit -3;
    }

}

sub agentOffReport{
    my $diagnosticfile = shift;
    my $va = VirtAgentInterface->new(); 
    
    $ENV{'CFG_FILE'}='/etc/sysconfig/etva-vdaemon/virtd.conf' unless $ENV{'CFG_FILE'};
    $va->loadsysinfo(); 
    open my $FILE, ">$diagnosticfile";
    my $CONF = ETVA::Utils::get_conf(); #1, '/etc/sysconfig/etva-vdaemon/virtd.conf');
    my $c_path = $CONF->{'CFG_FILE'};
    my $tar = new ETVA::ArchiveTar( 'handle'=> $FILE );
    $tar->add_file( 'name'=>"$c_path", 'path'=>"$c_path" );

    my $agent_logs = $ENV{'agent_log_dir'} || "/var/log/etva-vdaemon/*.log*";
    my @log_files = glob $agent_logs;

    # add agent logs
    for my $file (@log_files){
        $tar->add_file( 'name'=>"$file", 'path'=>"$file" );
        print "[INFO] adding file $file\n";
    }

    # adds agent info
    my $hostname = `hostname`;
    chomp $hostname;
    my $infofile = "/tmp/$hostname";
    $infofile .= '_info.txt';

#    VirtAgentInterface::get_agentinfo('self', $infofile);
    $va->get_agentinfo($infofile);
    my $sos = $va->get_sosreport();
    if($sos){
        my @sosfiles = glob $sos;
        print "[INFO] @sosfiles";
        for my $f (@sosfiles){
            print "[INFO] Adding file $f\n";
            $tar->add_file( 'name'=>$f, 'path'=>$f);        
        }
    }
    $tar->add_file( 'name'=>$infofile, 'path'=>$infofile );
    print "[INFO] adding file $infofile\n";

#    my @xml = VirtAgentInterface::vms_xml('self');
    my @xml = $va->vms_xml();
    print "[INFO] xml: @xml \n";
    foreach my $x (@xml){
        my %xmlh = %$x;
        $tar->add_file( 'name'=>$xmlh{'name'}, 'path'=>"", 'data'=>$xmlh{'xml'}, 'type'=>ETVA::ArchiveTar::FILE, 'mode'=>33204, 'mtime'=>ctime() );
        print "[INFO] adding file $xmlh{'name'}\n";
    }

    $tar->write();
    
    # remove temporary files
    `rm -rf $sos`;
    return;
}

#sub main {
#    my ($ip, $port) = @_;
#
#    my $res = `echo "POST /get_backupconf HTTP/1.1\ndiagnostic=1" | nc $ip $port`;
#    unless($res){
#        print STDERR "[ERROR] /get_backupconf doesn't retrieve an answer.\n";
#        exit -4;
#    }
#
#    my $s = open FILE, ">$output";
#    unless($s){
#        print STDERR "[ERROR] $!\n";
#    }
#    
#    # remove HTTP header and print file
#    my $rspheader = 1;
#    foreach my $line(split("\n",$res)){
#        $rspheader = 0 if($line =~ /^\//);
#        print FILE $line."\n" if($rspheader == 0);
#        #$rspheader = 0 if($line =~ /^\s*$/);
#    }
#    close FILE;
#    return 1;
#}

exit 0;

=back

=pod

=head1 BUGS

...

=head1 AUTHORS

...

=head1 COPYRIGHT

...

=head1 LICENSE

...

=head1 SEE ALSO

L<ETVA::Client>, L<ETVA::Client::SOAP>, L<ETVA::Client::SOAP::HTTP>
L<virtd>,
L<VirtAgentInterface>, L<VirtAgent::Disk>, L<VirtAgent::Network>,
L<VirtMachine>
L<ETVA::Agent>, L<ETVA::Agent::SOAP>, L<ETVA::Agent::JSON>
C<http://libvirt.org>

=cut
