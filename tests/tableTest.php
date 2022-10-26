<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/src/base/fmttable.php';
require_once dirname(__DIR__) . '/src/base/Slurp.php';

final class TableTest extends TestCase
{
    public function testRunner() : void
    {

        $table_def = array( "ID" => "ID",
                            "Repository" => "Repository",
                            "Tag" => "Tag",
                            "Created At" => "CreatedAt"
        );

        $table_data = <<<HERE
{"Containers":"N/A","CreatedAt":"2022-10-19 13:41:29 +0300 IDT","CreatedSince":"2 days ago","Digest":"\u003cnone\u003e","ID":"dc4cb6ed97b0","Repository":"crane","SharedSize":"N/A","Size":"665MB","Tag":"test-v3.6.12-73369","UniqueSize":"N/A","VirtualSize":"665.2MB"}
{"Containers":"N/A","CreatedAt":"2022-10-19 13:37:21 +0300 IDT","CreatedSince":"2 days ago","Digest":"\u003cnone\u003e","ID":"337263471e43","Repository":"crane","SharedSize":"N/A","Size":"665MB","Tag":"test-v3.6.12-72788","UniqueSize":"N/A","VirtualSize":"665.2MB"}
{"Containers":"N/A","CreatedAt":"2022-10-05 20:11:01 +0300 IDT","CreatedSince":"2 weeks ago","Digest":"\u003cnone\u003e","ID":"0f49cad59094","Repository":"golang","SharedSize":"N/A","Size":"841MB","Tag":"latest","UniqueSize":"N/A","VirtualSize":"840.8MB"}
{"Containers":"N/A","CreatedAt":"2022-04-11 18:25:34 +0300 IDT","CreatedSince":"6 months ago","Digest":"\u003cnone\u003e","ID":"157095baba98","Repository":"docker/getting-started","SharedSize":"N/A","Size":"27.4MB","Tag":"latest","UniqueSize":"N/A","VirtualSize":"27.37MB"}
{"Containers":"N/A","CreatedAt":"2020-08-09 02:10:26 +0300 IDT","CreatedSince":"2 years ago","Digest":"\u003cnone\u003e","ID":"96204f82e534","Repository":"quay.io/mmoser/s9k-mm","SharedSize":"N/A","Size":"334MB","Tag":"latest","UniqueSize":"N/A","VirtualSize":"333.5MB"}
HERE;

        $result_data = <<<HERE
<th>ID</th><th>Repository</th><th>Tag</th><th>Created At</th>
<tr><td>dc4cb6ed97b0</td><td>crane</td><td>test-v3.6.12-73369</td><td>2022-10-19 13:41:29 +0300 IDT</td>
</tr>
<tr><td>337263471e43</td><td>crane</td><td>test-v3.6.12-72788</td><td>2022-10-19 13:37:21 +0300 IDT</td>
</tr>
<tr><td>0f49cad59094</td><td>golang</td><td>latest</td><td>2022-10-05 20:11:01 +0300 IDT</td>
</tr>
<tr><td>157095baba98</td><td>docker/getting-started</td><td>latest</td><td>2022-04-11 18:25:34 +0300 IDT</td>
</tr>
<tr><td>96204f82e534</td><td>quay.io/mmoser/s9k-mm</td><td>latest</td><td>2020-08-09 02:10:26 +0300 IDT</td>
</tr>
HERE;

        $tbl_json = base\Slurp($table_data);
        $runner = new base\FmtTable($table_def);
        $result = $runner->format($tbl_json);
        $this->assertEquals($result, $result_data);
    }
}
