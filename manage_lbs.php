<style>
    .button {
        font: bold 11px Arial; text-decoration: none; background-color: #EEEEEE; color: #333333;
        padding: 2px 6px 2px 6px; border-top: 1px solid #CCCCCC; border-right: 1px solid #333333;
        border-bottom: 1px solid #333333; border-left: 1px solid #CCCCCC;
    }
</style>

<?php
$user_name = '';
$api_key = '';
$ipv4_only = true;  // true or false

class Authenticate {

    private $ident_url;
    private $content_type;

    public function __construct($user_name, $api_key) {
        $this->ident_url = 'https://identity.api.rackspacecloud.com/v2.0/tokens';
        $this->content_type = 'Content-Type: application/json';
        $this->user_name = $user_name;
        $this->api_key = $api_key;
    }

    public function request_auth() {
        $json_payload = json_encode(
            array(
                'auth' => array(
                    'RAX-KSKEY:apiKeyCredentials' => array(
                        'username' => $this->user_name,
                        'apiKey' => $this->api_key
                    )
                )
            )
        );

        $ch = curl_init($this->ident_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER,array($this->content_type)); // -H
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        $json_str = curl_exec($ch);
        $json_obj = json_decode($json_str);
        return $json_obj;
    }
}


class RetrieveData {

    private $region_url;
    private $content_type;

    public function __construct($token, $region_url, $marker, $url_details) {
        if ($marker == 0){
            $this->region_url = $region_url.'/'.$url_details.'?limit=100';
        }
        else {
            $this->region_url = $region_url.'/'.$url_details.'?limit=100&marker='.$marker;
        }
        $this->content_type = 'Accept: application/json';
        $this->auth_token = 'X-Auth-Token: '.$token;
    }

    public function request_data() {
        $ch = curl_init($this->region_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER,array($this->auth_token, $this->content_type)); // -H
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        $json_str = curl_exec($ch);
        $json_obj = json_decode($json_str);
        return $json_obj;
    }
}


class CreateSVIPLB {

    private $auth_token;
    private $new_lb_name;
    private $new_lb_vip;
    private $new_lb_id;
    private $new_lb_protocol;
    private $new_lb_port;
    private $new_lb_intport;
    private $new_lb_node;
    private $region_url;

    public function __construct($auth_token, $new_lb_name, $new_lb_vip, $new_lb_id, $new_lb_protocol, $new_lb_port, $new_lb_intport, $new_lb_node, $region_url) {
        $this->auth_token = 'X-Auth-Token: '.$auth_token;
        $this->content_type = 'Content-Type: application/json';
        $this->new_lb_name = $new_lb_name;
        $this->new_lb_vip = $new_lb_vip;
        $this->new_lb_id = $new_lb_id;
        $this->new_lb_protocol = $new_lb_protocol;
        $this->new_lb_port = $new_lb_port;
        $this->new_lb_intport = $new_lb_intport;
        $this->new_lb_node = $new_lb_node;
        $this->region_url = $region_url.'/loadbalancers';
    }

    public function create_lb() {
        $json_payload = json_encode(
            array(
                'loadBalancer' => array(
                    'name' => $this->new_lb_name,
                    'port' => $this->new_lb_port,
                    'protocol' => $this->new_lb_protocol,
                    'virtualIps' => array(
                        array(
                            'id' => $this->new_lb_id
                        )
                    ),
                    'nodes' => array(
                        array(
                            'address' => $this->new_lb_node,
                            'port' => $this->new_lb_intport,
                            'condition' => 'ENABLED'
                        )
                    )
                )
            )
        );

        $ch = curl_init($this->region_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER,array($this->auth_token, $this->content_type)); // -H
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $json_str = curl_exec($ch);
        $json_obj = json_decode($json_str);
        return $json_obj;
    }
}

/*  ================================================================================ */

$auth_obj = new Authenticate($user_name, $api_key);
$services = $auth_obj->request_auth();

// Short cut names for later
$token_obj = $services->access->token;
$cat_obj = $services->access->serviceCatalog;
$token = $token_obj->id; // Retrieved Authenticated token

if (isset($_POST['NewSVip']) && !empty($_POST["Name"])) {
    $new_lb_name = $_POST['Name'];
    list($new_lb_id, $new_lb_vip) = explode('|', $_POST['Vip']);
    $new_lb_protocol = $_POST['Protocol'];
    $new_lb_port = $_POST['Port'];
    $new_lb_intport = $_POST['Intport'];
    $new_lb_node = $_POST['Node'];
    $new_lb_region = $_POST['Region'];
    $new_lb_obj = new CreateSVIPLB($token, $new_lb_name, $new_lb_vip, $new_lb_id,
                                   $new_lb_protocol, $new_lb_port, $new_lb_intport,
                                   $new_lb_node, $new_lb_region);
    $rx = $new_lb_obj->create_lb();
}

foreach($cat_obj as $k1 => $v1) {
    if ($cat_obj[$k1]->type == 'rax:load-balancer') {
        $rax_lb_regs_obj = $cat_obj[$k1]->endpoints;
        foreach($rax_lb_regs_obj as $lbrk1 => $lbrv1){
            $lb_reg_arr[$rax_lb_regs_obj[$lbrk1]->region] = $rax_lb_regs_obj[$lbrk1]->publicURL;
        }
    }

    if (($cat_obj[$k1]->type == 'compute') && ($cat_obj[$k1]->name == 'cloudServersOpenStack')) {
        $rax_serv_regs_obj = $cat_obj[$k1]->endpoints;
        foreach($rax_serv_regs_obj as $srvrk1 => $srvrv1) {
            $serv_reg_arr[$rax_serv_regs_obj[$srvrk1]->region] = $rax_serv_regs_obj[$srvrk1]->publicURL;
        }
    }
}

uksort($lb_reg_arr, 'strcasecmp');

print "<a target='_blank' class='button' href='https://mycloud.rackspace.com/a/$user_name/load_balancers#new'>Create New LoadBalancer</a>";

foreach($lb_reg_arr as $reg_key => $reg_url) {
    $marker = 0;
    $lb_obj = new RetrieveData($token, $reg_url, $marker, 'loadbalancers');
    $reg_lb_obj = $lb_obj->request_data();
    $lb_cnt = count($reg_lb_obj->loadBalancers)."\n";
    if($lb_cnt == 0) {
        continue;
    }
    print "<center><h2>Region: $reg_key</h2></center>\n";
    print "<center><table cellpadding='2' cellspacing='0' border='1' width='100%'></center>";
    print "<tr><th style='width:35px;'>#</th><th style='width:70px;'>ID</th><th>LB Name</th><th>IP Address</th>
    <th>Proto:Port</th><th colspan='2'>Created(UTC)</th><th>Updated(UTC)</th><th>Nodes</th></tr>\n";
    $list_cnt = 0;
    $lbvip_arr = array();
    $lbid_arr = array();
    while(true) {
        foreach($reg_lb_obj->loadBalancers as $lb_key => $lb_val) {
            $list_cnt++;
            $list_id = $lb_val->id;
            $list_name = $lb_val->name;
            $list_proto = $lb_val->protocol;
            $list_port = $lb_val->port;
            $list_algorithm = $lb_val->algorithm;
            $list_status = $lb_val->status;
            $list_created = $lb_val->created->time;
            $list_updated = $lb_val->updated->time;
            $list_nodes = $lb_val->nodeCount;
            print "<tr align='center'><td>$list_cnt</td><td>
            <a target='_blank' href='https://mycloud.rackspace.com/a/$user_name/load_balancers#rax%3Aload-balancer%2CcloudLoadBalancers%2C$reg_key/$list_id'>
            $list_id</a></td><td align='left'>$list_name</td><td>";
            foreach($lb_val->virtualIps as $lbvip_key => $lbvip_val) {
                if(($lbvip_val->ipVersion == 'IPV4') || ($ipv4_only != true)) {
                  print "$lbvip_val->address</br>";
                  $lbvip_arr[$lbvip_val->address] = $list_name;
                  $lbid_arr[$lbvip_val->address] = $lbvip_val->id;
                }
            }
            print "</td><td>$list_proto:$list_port</td><td colspan='2'>$list_created</td><td>$list_updated</td><td>$list_nodes</td></tr>\n";
        }
        if($lb_key != 99) {
            break;
        }
        $lb_obj = new RetrieveData($token, $reg_url, ($lb_val->id + 1), 'loadbalancers');
        $reg_lb_obj = $lb_obj->request_data();
    }
    print '<form method="POST" action="">';
    print "<tr align='center' valign='bottom'><td colspan='2' style='height:60px;'>
    <input name='NewSVip' type='submit' value='New Shared VIP LB'>
    <input name='Region' type='hidden' value='$reg_url'>
    </td>
    <td>Name:<br><input name='Name' type='text' style='width:100%;'></td>
    <td nowrap='nowrap'>Server Example / Shared Vip:<br><select name='Vip' style='width:100%;'>";
    foreach($lbvip_arr as $serv_ip => $serv_name) {
        print "<option value='$lbid_arr[$serv_ip]|$serv_ip'>$serv_name / $serv_ip</option>";
    }
    print "</select></td>";
    print "<td>Protocol:<br><select name='Protocol' style='width:100%;'>
        <option value='DNS_TCP'>DNS(TCP)</option>
        <option value='DNS_UDP'>DNS(UDP)</option>
        <option value='FTP'>FTP</option>
        <option value='HTTP'>HTTP</option>
        <option value='HTTPS'>HTTPS</option>
        <option value='IMAPS'>IMAPS</option>
        <option value='IMAPv2'>IMAPv2</option>
        <option value='IMAPv3'>IMAPv3</option>
        <option value='IMAPv4'>IMAPv4</option>
        <option value='LDAP'>LDAP</option>
        <option value='LDAPS'>LDAPS</option>
        <option value='MYSQL'>MySQL</option>
        <option value='POP3'>POP3</option>
        <option value='POP3S'>POP3S</option>
        <option value='SFTP'>SFTP</option>
        <option value='SMTP'>SMTP</option>
        <option value='TCP'>TCP</option>
        <option value='TCP_CLIENT_FIRST'>TCP(Client First)</option>
        <option value='UDP'>UDP</option>
        <option value='UDP_STREAM'>UDP(Stream)</option>
    </select></td>";
    print "<td>Ext Port:<br><input name='Port' type='text' style='width:60px;'></td>";
    print "<td>Int Port:<br><input name='Intport' type='text' style='width:60px;'></td>";

    $servers_obj = new RetrieveData($token, $serv_reg_arr[$reg_key], $marker, 'servers/detail');
    $serv_list_obj = $servers_obj->request_data();

    print "<td colspan='2' nowrap='nowrap'>Initial Node (2ndGen CS) / PublicIP:<br><select name=Node style='width:100%;'>";
    foreach($serv_list_obj->servers as $servlist_key => $servlist_val) {
        $private_ip = $servlist_val->addresses->private[0]->addr;
        print "<option value='$private_ip'>$servlist_val->name / $private_ip </option>";
    }
    print "</select></td>";

    print "</tr></table>";
    print '</form>';
}
