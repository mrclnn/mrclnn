<?php

namespace App\Http\Controllers;

use App\Calls;
use http\Exception\RuntimeException;
use Illuminate\Http\Request;
use mysqli;

class CallsController extends Controller
{
    public function execute(){

        $db = new mysqli(
            '192.168.105.45',
            'itcenter_itest',
            '6!9j*LS5',
            'itcenter_web_widget'
        );

        $query = <<<GET_FROM_CALLS
SELECT
    calls.number,
    calls.date,
    calls.did,
    calls.client,
    calls.utms,
    calls.referrer_uri,
    calls.location_uri
FROM itcenter_web_calltracking.calls
WHERE client = ? AND date > ? and date < ?
GET_FROM_CALLS;
        $stmt = $db->prepare($query);
        if(!$stmt){
            throw new RuntimeException(sprintf('Failed to prepare query: [%s] %s', $db->errno, $db->error));
        }
        $client = 43;
        $dateBeg = '2021-03-01';
        $dateEnd = '2021-03-02';
        $stmt->bind_param('iss', $client, $dateBeg, $dateEnd);
        $stmt->bind_result(
            $number,
            $date,
            $did,
            $client,
            $utms,
            $referrer_uri,
            $location_uri
        );
        $querySuccess = $stmt->execute();
        if (!$querySuccess) {
            throw new RuntimeException(
                sprintf('Failed to execute sql query in %s [%s]: %s', __METHOD__, $stmt->errno, $stmt->error)
            );
        }
        $success = $stmt->fetch();
        $stmt->free_result();

        var_dump($success);





    }
}
