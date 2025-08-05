    </div> <!-- End of container -->
    <style>
        footer {
            background: linear-gradient(135deg, #1a365d, #2d3748);
            color: #e2e8f0;
            padding: 2rem 1.25rem;
            margin-top: auto;
            position: relative;
            overflow: hidden;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, 
                transparent 0%, 
                #60a5fa 50%, 
                transparent 100%
            );
            opacity: 0.7;
        }

        .footer-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
            gap: 2rem;
            position: relative;
            z-index: 1;
        }

        .footer-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .footer-logo {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            background: #ffffff;
            padding: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .copyright {
            font-size: var(--font-size-base);
            font-weight: 400;
            color: #cbd5e1;
            letter-spacing: 0.3px;
        }

        .credits {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: var(--font-size-sm);
            color: #60a5fa;
            font-weight: 500;
            position: relative;
            padding: 0.5rem 1.5rem;
            background: rgba(96, 165, 250, 0.1);
            border-radius: 30px;
            transition: var(--transition-all);
            border: 1px solid rgba(96, 165, 250, 0.2);
        }

        .credits:hover {
            background: rgba(96, 165, 250, 0.15);
            transform: translateY(-1px);
            border-color: rgba(96, 165, 250, 0.3);
        }

        .credits i {
            font-size: 1.1em;
            color: #60a5fa;
        }

        .credits .divider {
            width: 1px;
            height: 1em;
            background: rgba(96, 165, 250, 0.3);
            margin: 0 0.5rem;
        }

        .credits .developer {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .credits .developer img {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid rgba(96, 165, 250, 0.3);
            background: #ffffff;
            padding: 2px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .credits .developer span {
            font-weight: 600;
            background: linear-gradient(135deg, #60a5fa, #818cf8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Subtle background pattern */
        footer::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(96, 165, 250, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(96, 165, 250, 0.1) 0%, transparent 50%);
            opacity: 0.5;
        }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--neutral-50);
        }

        @media (max-width: 768px) {
            footer {
                padding: 1.5rem 1rem;
            }

            .footer-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .footer-left {
                flex-direction: column;
                gap: 0.5rem;
            }

            .copyright {
                font-size: var(--font-size-sm);
            }

            .credits {
                font-size: var(--font-size-xs);
                padding: 0.4rem 1.2rem;
            }
        }

        @media (max-width: 480px) {
            footer {
                padding: 1.25rem 0.75rem;
            }

            .copyright {
                font-size: var(--font-size-xs);
            }

            .credits {
                font-size: var(--font-size-xs);
                padding: 0.35rem 1rem;
                flex-direction: column;
                gap: 0.25rem;
            }

            .credits .divider {
                display: none;
            }
        }

        /* Dark theme adjustments */
        [data-theme="dark"] footer {
            background: linear-gradient(135deg, #0f172a, #1e293b);
        }

        [data-theme="dark"] .copyright {
            color: #94a3b8;
        }

        [data-theme="dark"] .credits {
            background: rgba(96, 165, 250, 0.15);
            border-color: rgba(96, 165, 250, 0.3);
        }

        [data-theme="dark"] .credits:hover {
            background: rgba(96, 165, 250, 0.2);
            border-color: rgba(96, 165, 250, 0.4);
        }

        [data-theme="dark"] .credits .divider {
            background: rgba(96, 165, 250, 0.4);
        }
    </style>

    <footer>
        <div class="footer-content">
            <div class="footer-left">
                <img src="/complaint_portal/assets/images/aimt-logo.png" alt="AIMT" class="footer-logo">
                <div class="copyright">
                    © <?php echo date('Y'); ?> Army Institute of Management and Technology, Greater Noida
                </div>
            </div>
            <div class="credits">
            <i class="fa-solid fa-code" aria-hidden="true"></i>
                <span>Designed & Developed By</span>
                <div class="divider"></div>
                <div class="developer">
                    <img src="/complaint_portal/assets/images/aimt-logo.png" alt="AIMT">
                    <span>Student of AIMT</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- 
    /////////////////////////////////////////////////////////////////////
    //                                                                   //
    //     [!] SYSTEM SIGNATURE DETECTED [!]                            //
    //     ================================                            //
    //                                                                 //
    //     >> DEVELOPER: MR.HARKAMAL                                  //
    //     >> STATUS: AUTHORIZED                                      //
    //     >> ACCESS_LEVEL: ROOT                                      //
    //     >> TIMESTAMP: 2025                                         //
    //     >> LOCATION: AIMT_GREATER_NOIDA                           //
    //                                                               //
    //     [SYSTEM_MESSAGE]                                         //
    //     {                                                        //
    //        "creator": "MR.HARKAMAL",                            //
    //        "mission": "Crafted through sleepless nights",       //
    //        "purpose": "Serve AIMT students",                    //
    //        "status": "DEPLOYED"                                 //
    //     }                                                       //
    //                                                            //
    //     >> ENCRYPTED_SIGNATURE:                               //
    //     H4X0R-HKML-2025-AIMT                                //
    //                                                         //
    //     [END_TRANSMISSION]                                 //
    //     =====================================             //
    //     "Through Adversity to the Stars"                //
    //                                                    //
    ////////////////////////////////////////////////////
    -->

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Add loading state to buttons
        document.querySelectorAll('button, .btn').forEach(button => {
            button.addEventListener('click', function() {
                if (!this.classList.contains('no-loading')) {
                    this.classList.add('loading');
                }
            });
        });

        // Add smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html> 