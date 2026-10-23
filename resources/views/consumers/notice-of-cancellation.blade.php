@extends('consumers.layout')

@section('title', 'Notice of Cancellation | Apex Growth Solutions')
@section('description', 'Your right to cancel a contract with a credit repair organization within three business days, and the cancellation notice form.')

@section('content')
<section class="page-hero">
    <div class="container">
        <span class="eyebrow">Legal</span>
        <h1>Notice of Cancellation</h1>
        <p>You have the right to cancel your contract for any reason within three business days.</p>
    </div>
</section>

<section class="section">
    <div class="container legal">
        <h2 style="margin-top:0;">Your right to cancel</h2>
        <p>Under the federal Credit Repair Organizations Act (15 U.S.C. § 1679e), you may cancel any contract with a credit repair organization, without penalty or obligation, by notifying the organization before midnight of the 3rd business day that begins after the date the contract is signed. Every contract you sign with Apex Growth Solutions comes with this form, in duplicate.</p>

        <h2>The cancellation form</h2>
        <div class="cancel-box">
            <p>NOTICE OF CANCELLATION</p>
            <p>You may cancel this contract, without any penalty or obligation, at any time before midnight of the 3rd day which begins after the date the contract is signed by you.</p>
            <p>To cancel this contract, mail or deliver a signed, dated copy of this cancellation notice, or any other written notice to <strong>Apex Growth Solutions</strong> at <strong>the address shown on your service agreement</strong>, or email it to <strong>apexgrow@apexgrowthsolution.com</strong>, before midnight on <strong>[the date shown on your contract]</strong>.</p>
            <p>I hereby cancel this transaction,</p>
            <div class="line"></div><div class="note">Date</div>
            <div class="line"></div><div class="note">Purchaser's signature</div>
        </div>

        <p class="updated">The form above reproduces the wording required by 15 U.S.C. § 1679e(b). The signed copy of the contract you receive will show the cancellation deadline date. If you have any questions, <a href="{{ route('consumers.contact') }}">contact us</a>, or read your <a href="{{ route('consumers.credit-rights') }}">Consumer Credit File Rights</a>.</p>
    </div>
</section>
@endsection
