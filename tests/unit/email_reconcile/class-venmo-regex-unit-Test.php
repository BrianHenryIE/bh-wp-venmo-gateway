<?php

namespace BrianHenryIE\WP_Venmo_Gateway\API;

use BrianHenryIE\WP_Venmo_Gateway\Unit_Testcase;

class Venmo_Regex_Unit_Test extends Unit_Testcase {

	public function test_amount_1() {

		$str = <<<'EOD'
        <!-- date, audience, and amount -->
            <span>Apr 01, 2021 PDT</span>
            <span> · </span>
            <img style="vertical-align: -1px;" src=https://s3.amazonaws.com/venmo/audience/private.png alt="private"/>

            <!-- amount -->


                <div style="float:right; text-align: right;">
                    <div>$71.24</div>
                    <div>Fee - $1.45</div>
                    <div style="color:#009200;">+ $69.79</div>
                </div>


        </td>
EOD;

		$str = preg_replace( '/\s+/', ' ', $str );

		$patterns = new Pattern_2();

		preg_match( $patterns->get_amount_regex(), $str, $output );

		$this->assertEquals( 71.24, $output[1] );
	}

	public function test_amount_2() {

		$str = <<<'EOD'
<!-- date, audience, and amount -->
<span>Apr 02, 2021 PDT</span>
<span> · </span>
<img style="vertical-align: -1px;" src=https://s3.amazonaws.com/venmo/audience/private.png alt="private"/>

<!-- amount -->


<span style="color:#009200;float:right;">+ $14.99</span>


</td>
EOD;
		$str = preg_replace( '/\s+/', ' ', $str );

		$patterns = new Pattern_2();

		preg_match( $patterns->get_amount_regex(), $str, $output );

		$this->assertEquals( 14.99, $output[1] );
	}



	public function test_amount_3() {

		$str = <<<'EOD'
<!-- date, audience, and amount -->
<span>Apr 02, 2021 PDT</span>
<span> · </span>
<img style="vertical-align: -1px;" src=https://s3.amazonaws.com/venmo/audience/private.png alt="private"/>
            <img style="vertical-align: -1px;" src=https://s3.amazonaws.com/venmo/audience/private.png alt="private"/>

            <!-- amount -->


                <span style="color:#009200;float:right;">+ $94.97</span>


        </td>
EOD;
		$str = preg_replace( '/\s+/', ' ', $str );

		$patterns = new Pattern_2();

		preg_match( $patterns->get_amount_regex(), $str, $output );

		$this->assertEquals( 94.97, $output[1] );
	}
}
