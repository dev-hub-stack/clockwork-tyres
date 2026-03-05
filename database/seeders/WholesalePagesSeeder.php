<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WholesalePagesSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'slug'       => 'terms-conditions',
                'title'      => 'Terms of Service',
                'content'    => '<p class="MsoNormal" style="mso-outline-level: 2; background: white; margin: 15.0pt 0in 15.0pt 0in;"><strong><span lang="en-AE" style="font-size: 15.0pt; font-family: Montserrat; color: black; text-transform: uppercase;">TERMS &amp; CONDITIONS</span></strong></p>
<p>The materials on this site ("Site") are provided by TunerStop Tyres &amp; Accessories Trading LLC. ("TunerStop") and may be used only for informational purposes. By using the Site you agree to be bound by these terms and to comply with all applicable laws and regulations. If you do not agree to these terms, you should not use this Site, so please review the terms carefully.</p>
<p>TunerStop reserves the right, in its sole discretion, to change, modify or otherwise alter these terms and conditions at any time and the same shall immediately become effective upon the posting. By use of this Site after such posting you agree to be bound by updates and changes. Further TunerStop reserves the right, in its sole discretion, to edit or delete any documents, information or other content appearing on the Site.</p>
<p>The content, organization, gathering, compilation, magnetic translation, digital conversion and other matters related to the Site are protected under applicable copyrights, trademarks and other proprietary and intellectual property rights, and you do not acquire ownership rights to any Site content or material. Permission is granted to display, copy, distribute and download materials on this Site for noncommercial purposes only, provided the materials are not modified, all copyright and other proprietary notices are kept intact, and none of the software material is decompiled, reverse engineered or disassembled.</p>
<p>ALL MATERIALS ON THIS SITE ARE PROVIDED "AS IS" WITHOUT ANY KIND EITHER EXPRESS OR IMPLIED. TO THE FULLEST EXTENT POSSIBLE PURSUANT TO APPLICABLE LAW, TUNERSTOP HEREBY DISCLAIMS ALL WARRANTIES, EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO, IMPLIED WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, TITLE AND NONINFRINGEMENT.</p>
<p>All responsibility or liability for any damages caused by viruses that may infect your computer equipment on account of your access to, use of or browsing in the Site, or downloading of any materials from this Site, is your responsibility and not that of TunerStop.</p>
<p>The site may contain links to other sites on the Internet, which are owned and operated by third parties. You acknowledge that TunerStop is not responsible for the availability of, or the content located on or through, any such site. TunerStop is providing these links to you only as a convenience and the inclusion of any link does not imply endorsement by TunerStop of the site.</p>
<p>TunerStop reserves the right, and you authorize TunerStop to the use and assignment of all information regarding Site uses by you and all information you may provide.</p>
<p>You agree that the contents of this site is confidential to you and your associates and should not be distributed to unauthorized parties.</p>
<p>You agree to indemnify, defend and save TunerStop, its employees and affiliates harmless from and against any liability, loss, claim, litigation and expense, including reasonable attorney\'s fees, related to your violation of this Agreement or use of the Site.</p>
<p>This agreement constitutes the entire agreement between TunerStop and you, as a user of the Site, and supersedes all prior agreements and understandings with respect to the Site, its content and materials and the subject matter of this Agreement. This Agreement shall be governed by the laws of the United Arab Emirates without regard to its conflict of laws or provisions.</p>',
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug'       => 'privacy-policy',
                'title'      => 'Privacy Policy',
                'content'    => '<p><strong>PRIVACY POLICY</strong></p>
<p>This site is owned and operated by TunerStop Tyres &amp; Accessories Trading LLC. This policy is relevant to all Internet properties owned by Tyres &amp; Accessories Trading LLC.</p>
<p><strong>TYPE OF INFORMATION WE COLLECT</strong></p>
<p>Tyres &amp; Accessories Trading LLC collects information if you visit our Internet properties AND register for a contest or a newsletter, purchase products, obtain information regarding one of our products, enroll as a member in an online community owned by Tyres &amp; Accessories Trading LLC, request other information, or otherwise communicate with us about our offerings. The information we collect about you can include name, address, telephone number, e-mail address, credit card number and expiration date, password, purchase history, IP address, what web sites visitors come from, which pages are visited at this web site, and information about online activities that are directly linked to them.</p>
<p>Tyres &amp; Accessories Trading LLC uses Google Analytics to collect data about our traffic via Google advertising cookies and identifiers, in addition to data collected through a standard Google Analytics implementation.</p>
<p><strong>USE OF THIS INFORMATION</strong></p>
<p>Tyres &amp; Accessories Trading LLC will collect and use your information to render services to you; respond to your inquiries; market and sell to you products, programs, and services offered by Tyres &amp; Accessories Trading LLC. Tyres &amp; Accessories Trading LLC will not sell or rent your information to any other companies or affiliates.</p>
<p>If necessary for the purposes mentioned above, Tyres &amp; Accessories Trading LLC may transfer your information to third parties and these third parties may use your information for such purposes. These third parties may include dealers, advisors, companies providing products and services to TunerStop and, to the extent required by law, regulatory authorities (including tax authorities).</p>
<p>If the version of this Internet property you are using allows you to purchase products or services online, your information will be used to complete any transactions you wish to enter into, including credit card processing and credit checks.</p>
<p>In addition, in the event that all or any part of Tyres &amp; Accessories Trading LLC is sold to a third party, we may transfer your information to the third party buyer as part of the assets of the business.</p>
<p>We may share anonymous aggregated demographic and general trend information with any third party we choose.</p>
<p><strong>OUR USE OF &ldquo;COOKIES&rdquo; AND OTHER COMPUTER INFORMATION</strong></p>
<p>As is the case with many sites, when you visit Internet properties and complete a registration form, we will place a &ldquo;cookie&rdquo; on your computer, which helps us identify you more quickly when you return. Cookies are small pieces of information stored by your browser on your computer\'s hard drive and are used in website system administration to keep track of movement of an individual from one screen to another. We will use &ldquo;cookies&rdquo; alone or in conjunction with other devices to understand how you arrived at our web sites, to determine which pages or information you find most useful or interesting, and to make choices about how to deliver the most relevant marketing messages about our products to you.</p>',
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($pages as $page) {
            DB::table('wholesale_pages')->updateOrInsert(
                ['slug' => $page['slug']],
                $page
            );
        }
    }
}
