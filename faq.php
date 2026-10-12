<?php

$pageTitle = "FAQ - Scholarship Finder";

require_once __DIR__ . '/includes/header.php';

?>

<div class="row justify-content-center">
    <div class="col-lg-8">

        <h1 class="mb-4">Frequently Asked Questions</h1>

        <div class="accordion" id="faqAccordion">

            <div class="accordion-item">

                <h2 class="accordion-header">
                    <button
                        class="accordion-button"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#faqOne"
                    >
                        What is Scholarship Finder?
                    </button>
                </h2>

                <div
                    id="faqOne"
                    class="accordion-collapse collapse show"
                    data-bs-parent="#faqAccordion"
                >

                    <div class="accordion-body">
                        Scholarship Finder is a website designed to help
                        students and families locate scholarship opportunities.
                    </div>

                </div>
            </div>


            <div class="accordion-item">

                <h2 class="accordion-header">
                    <button
                        class="accordion-button collapsed"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#faqTwo"
                    >
                        Do I need an account to browse scholarships?
                    </button>
                </h2>

                <div
                    id="faqTwo"
                    class="accordion-collapse collapse"
                    data-bs-parent="#faqAccordion"
                >

                    <div class="accordion-body">
                        No. Visitors can browse public scholarship information
                        without creating an account.
                    </div>

                </div>
            </div>


            <div class="accordion-item">

                <h2 class="accordion-header">
                    <button
                        class="accordion-button collapsed"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#faqThree"
                    >
                        What grade levels does the site support?
                    </button>
                </h2>

                <div
                    id="faqThree"
                    class="accordion-collapse collapse"
                    data-bs-parent="#faqAccordion"
                >

                    <div class="accordion-body">
                        The website supports scholarship information for
                        students in grades 6 through 12.
                    </div>

                </div>
            </div>


            <div class="accordion-item">

                <h2 class="accordion-header">
                    <button
                        class="accordion-button collapsed"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#faqFour"
                    >
                        Can parents use the website?
                    </button>
                </h2>

                <div
                    id="faqFour"
                    class="accordion-collapse collapse"
                    data-bs-parent="#faqAccordion"
                >

                    <div class="accordion-body">
                        Yes. Parents can use the website to help students
                        locate and review scholarship opportunities.
                    </div>

                </div>
            </div>


            <div class="accordion-item">

                <h2 class="accordion-header">
                    <button
                        class="accordion-button collapsed"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#faqFive"
                    >
                        How do I apply for a scholarship?
                    </button>
                </h2>

                <div
                    id="faqFive"
                    class="accordion-collapse collapse"
                    data-bs-parent="#faqAccordion"
                >

                    <div class="accordion-body">
                        Scholarship listings will provide information about
                        the opportunity and how students can learn more or apply.
                    </div>

                </div>
            </div>

        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
