@extends('consumers.layout')

@section('title', 'Terms of Service | Apex Growth Solutions')
@section('description', 'The terms for using the Apex Growth Solutions website and how they relate to your written service agreement.')

@section('content')
<section class="page-hero">
    <div class="container">
        <span class="eyebrow">Legal</span>
        <h1>Terms of Service</h1>
        <p>The rules for using this website.</p>
    </div>
</section>

<section class="section">
    <div class="container legal">
        <p class="updated">Effective date: October 24, 2026</p>

        <p>These Terms of Service ("Terms") apply to your use of this website, operated by Apex Growth Solutions ("Apex Growth Solutions", "we", "us" or "our"). By using the website you agree to these Terms. If you don't agree, please don't use it.</p>

        <h2>1. What we do</h2>
        <p>Apex Growth Solutions provides credit education, credit report analysis and dispute assistance. This website describes our services and lets you contact us. It is not an offer that binds either of us. Any services we provide to you are governed by a separate written service agreement that you sign.</p>

        <h2>2. We are not a law firm</h2>
        <p>We are not a law firm and do not provide legal advice. Nothing on this website is legal advice or creates an attorney-client relationship. If you need legal advice, speak to a licensed attorney.</p>

        <h2>3. No guarantees</h2>
        <p>We do not guarantee any particular result, including the removal of any item from your credit reports or any increase in your credit score. Accurate, current and verifiable information can't lawfully be removed from your credit reports by us or by anyone else. Results vary based on each consumer's credit profile and circumstances. Examples, testimonials and illustrations on this website reflect individual experiences and are not typical.</p>

        <h2>4. Your rights as a consumer</h2>
        <p>Before you sign a contract with us you will receive a written statement of your <a href="{{ route('consumers.credit-rights') }}">Consumer Credit File Rights Under State and Federal Law</a>, a written contract, and a <a href="{{ route('consumers.cancellation') }}">Notice of Cancellation</a>. Federal law gives you the right to cancel a contract with a credit repair organization for any reason within 3 business days from the date you sign it. You also have the right to dispute inaccurate information in your credit reports directly with the credit bureaus at no cost.</p>

        <h2>5. Fees and payment</h2>
        <p>The packages and prices shown on this website are a summary. The services included, the fees, and when and how they are payable are set out in the written service agreement you sign. You will not be charged anything through this website's contact form. Credit monitoring is a separate subscription provided and billed by a third party, and it is not included in our fees.</p>

        <h2>6. Eligibility</h2>
        <p>You must be at least 18 years old to use this website and to enter into a service agreement with us.</p>

        <h2>7. Information you give us</h2>
        <p>You agree that the information you give us is accurate and complete, and that you will not submit information about another person without their permission. Our use of your information is described in our <a href="{{ route('consumers.privacy') }}">Privacy Policy</a>.</p>

        <h2>8. Acceptable use</h2>
        <p>You agree not to misuse this website. This includes trying to gain unauthorized access, interfering with its operation, sending spam or malicious code, scraping it at scale, or using it for any unlawful purpose.</p>

        <h2>9. Intellectual property</h2>
        <p>The content on this website, including text, graphics, logos and design, belongs to Apex Growth Solutions or its licensors and is protected by law. You may view and print pages for your own personal use. You may not copy, reproduce or distribute them for any other purpose without our written permission.</p>

        <h2>10. Third-party websites and services</h2>
        <p>This website may link to third-party websites, such as credit monitoring providers or government agencies. We don't control them and are not responsible for their content or practices.</p>

        <h2>11. Disclaimer of warranties</h2>
        <p>This website and its content are provided "as is" and "as available". To the fullest extent permitted by law, we disclaim all warranties, express or implied, including warranties of merchantability, fitness for a particular purpose and non-infringement. We do not warrant that the website will be uninterrupted or error-free.</p>

        <h2>12. Limitation of liability</h2>
        <p>To the fullest extent permitted by law, Apex Growth Solutions will not be liable for any indirect, incidental, special, consequential or punitive damages arising from your use of this website. This section does not limit any rights you have under the Credit Repair Organizations Act or other consumer protection laws that can't be limited by agreement, and it does not apply to the services provided under your written service agreement, which has its own terms.</p>

        <h2>13. Governing law and disputes</h2>
        <p>These Terms are governed by applicable federal law and the laws that apply to your relationship with us under your written service agreement. Nothing in these Terms removes any consumer right that the law doesn't allow to be waived. The governing law and how disputes are resolved for the services we provide to you are set out in your service agreement.</p>

        <h2>14. Changes to these Terms</h2>
        <p>We may update these Terms from time to time. The effective date above shows when they were last changed. Continued use of the website after a change means you accept the updated Terms.</p>

        <h2>15. Contact us</h2>
        <ul>
            <li>Email: <a href="mailto:apexgrow@apexgrowthsolution.com">apexgrow@apexgrowthsolution.com</a></li>
            <li>WhatsApp: <a href="https://wa.me/923013061491" target="_blank" rel="noopener">+92 301 3061491</a></li>
        </ul>
    </div>
</section>
@endsection
