<section class="welcome-stage" data-welcome-stage>
    <div class="welcome-glow welcome-glow-pink" aria-hidden="true"></div>
    <div class="welcome-glow welcome-glow-blue" aria-hidden="true"></div>
    <div class="welcome-card" data-welcome-card>
        <div class="welcome-topline">
            <img class="welcome-logo" src="<?= e(basePath()) ?>/assets/campus2career-logo.png" width="1056" height="181" alt="Campus2Career — Learn, Apply, Grow">
            <span class="welcome-status"><span aria-hidden="true"></span> YOUR NEXT CHAPTER STARTS HERE</span>
        </div>
        <div class="welcome-content">
            <div class="welcome-copy">
                <span class="welcome-eyebrow">LEARN <i aria-hidden="true"></i> APPLY <i aria-hidden="true"></i> GROW</span>
                <h1>Big ambitions.<br><span>Real opportunities.</span></h1>
                <p>Your next step is closer than you think. Choose what brings you here and we’ll take you to the right place.</p>
                <form class="welcome-form" action="<?= e(basePath()) ?>/index.php" method="get" data-welcome-form>
                    <input type="hidden" name="type" value="" data-welcome-type>
                    <fieldset>
                        <legend>What would you like to do?</legend>
                        <div class="welcome-choices">
                            <label class="welcome-choice">
                                <input class="sr-only" type="radio" name="page" value="opportunities" required>
                                <span class="welcome-choice-icon" aria-hidden="true">↗</span>
                                <span class="welcome-choice-copy"><strong>Find opportunities</strong><small>Explore jobs, internships & more</small></span>
                                <span class="welcome-choice-check" aria-hidden="true">✓</span>
                            </label>
                            <label class="welcome-choice">
                                <input class="sr-only" type="radio" name="page" value="register">
                                <span class="welcome-choice-icon" aria-hidden="true">✦</span>
                                <span class="welcome-choice-copy"><strong>Start my journey</strong><small>Create your career profile</small></span>
                                <span class="welcome-choice-check" aria-hidden="true">✓</span>
                            </label>
                            <label class="welcome-choice">
                                <input class="sr-only" type="radio" name="page" value="register" data-welcome-employer>
                                <span class="welcome-choice-icon" aria-hidden="true">◎</span>
                                <span class="welcome-choice-copy"><strong>Find emerging talent</strong><small>Join as an employer or partner</small></span>
                                <span class="welcome-choice-check" aria-hidden="true">✓</span>
                            </label>
                        </div>
                    </fieldset>
                    <div class="welcome-actions">
                        <button class="welcome-continue" type="submit" data-welcome-continue>Choose your path <span aria-hidden="true">→</span></button>
                        <a class="welcome-signin" href="<?= e(pageUrl('login')) ?>">Already have an account? <strong>Sign in</strong></a>
                    </div>
                </form>
            </div>
            <div class="welcome-art" aria-hidden="true">
                <div class="welcome-orbit welcome-orbit-one"></div>
                <div class="welcome-orbit welcome-orbit-two"></div>
                <div class="welcome-orbit welcome-orbit-three"></div>
                <div class="welcome-art-core"><span>c2c</span><i></i></div>
                <div class="welcome-float welcome-float-learn"><span>01</span> Learn</div>
                <div class="welcome-float welcome-float-apply"><span>02</span> Apply</div>
                <div class="welcome-float welcome-float-grow"><span>03</span> Grow</div>
                <span class="welcome-spark welcome-spark-one"></span>
                <span class="welcome-spark welcome-spark-two"></span>
                <span class="welcome-spark welcome-spark-three"></span>
                <div class="welcome-art-caption"><span></span> Your future is in motion</div>
            </div>
        </div>
        <div class="welcome-bottomline"><span>Built for Ghana’s next generation of talent.</span><span><i></i> Make your next move</span></div>
    </div>
</section>
