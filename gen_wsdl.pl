#!/usr/bin/perl

use File::Copy;

my $dir = ".";
my $backup_dir = "$dir/backup";


my $s_wsdl = "$dir/zconnector.wsdl";

my $addon = time;
my $b_wsdl = "$backup_dir/zconnector_$addon.wsdl";

my $f_file = "$dir/classes/zconnector.php";


#copy($s_wsdl, $b_wsdl);

my @flist;
my $zconn = 0;
open FLIST, "<$f_file" or die "Can't open $f_file: $!\n";
while (<FLIST>) {
	$zconn = 1 if (/class Zconnector/);

	if (/function __([a-zA-Z]+.+?) *\(/) {
		push @flist, $1;
	}

	last if (/^}$/ && $zconn);
}
close FLIST;

my @wsdl_data;
open WSDL, "<$s_wsdl" or die "Can't open $s_wsdl: $!\n";
@wsdl_data = <WSDL>;
close WSDL;

my @output;
my $state = 0;
foreach my $string (@wsdl_data) {

	if ($state == 1) {
		next if ($string !~ /<service name=/);

		@data = &do_main_work;

		push @output, @data;
		$state = 0;
	}

	push @output, $string;

	if ($string =~ /<\/types>/) {
		$state = 1;
	}





}
#foreach my $string (@output) {
#	print $string;
#}

open WSDL, ">$s_wsdl" or die "Can't open $s_wsdl: $!\n";

foreach my $string (@output) {
	print WSDL $string;
}

close WSDL;



sub do_main_work {
	my @data;


	
	foreach my $f (@flist) {
		my $p = qq(
<message name="${f}RequestMsg">
	<part name="${f}RequestMsgReq" element="tns:${f}"/>
</message>
<message name="${f}ResponseMsg">
	<part name="${f}MsgReq" element="tns:${f}Response"/>
</message>
);
		push @data, $p;
	}

	push @data, '<portType name="CxZConnectorPortType">';

	foreach my $f (@flist) {
		my $p = qq(
	<operation name="${f}">
		<input message="tns:${f}RequestMsg"/>
		<output message="tns:${f}ResponseMsg"/>
	</operation>		
);

		push @data, $p;
	}

	push @data, '</portType>
<binding type="tns:CxZConnectorPortType" name="CxZConnectorBinding">
	<soap:binding transport="http://schemas.xmlsoap.org/soap/http" style="document"/>';

	foreach my $f (@flist) {
		my $p = qq(
	<operation name="${f}">
		<soap:operation soapAction="${f}"/>
		<input>
			<soap:body use="literal"/>
		</input>
		<output>
			<soap:body use="literal"/>
		</output>
	</operation>
);
		push @data, $p;
	}

	push @data, '</binding>';
	push @data, "\n";

	return @data;
}

