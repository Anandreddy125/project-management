pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
        // Preserve stashes for debugging if needed
        preserveStashes(buildCount: 5)
    }

    environment {
        GIT_CREDENTIALS_ID    = "terra-github"
        DOCKER_CREDENTIALS_ID = "anand-dockerhub"
        IMAGE_REPO            = "anrs125/reports-tesing"
        
        // Default values
        DEPLOY_ENV = ""
        TAG_TYPE   = ""
        IMAGE_TAG  = ""
    }

    stages {
        /* ================= INITIAL SETUP ================= */
        stage('Initialize & Clean') {
            steps {
                cleanWs()
                echo """
                ===========================================
                MULTIBRANCH PIPELINE STARTED
                ===========================================
                """
            }
        }

        /* ================= GIT CHECKOUT & VERIFICATION ================= */
        stage('Checkout & Git Info') {
            steps {
                checkout([
                    $class: 'GitSCM',
                    branches: scm.branches,
                    extensions: scm.extensions + [
                        [$class: 'CleanBeforeCheckout'],
                        [$class: 'CloneOption', depth: 1, noTags: false, shallow: true]
                    ],
                    userRemoteConfigs: scm.userRemoteConfigs
                ])
                
                script {
                    // Get detailed Git information
                    def GIT_COMMIT_SHORT = sh(
                        script: "git rev-parse --short HEAD",
                        returnStdout: true
                    ).trim()
                    
                    def GIT_COMMIT_MESSAGE = sh(
                        script: "git log -1 --pretty=%B",
                        returnStdout: true
                    ).trim()
                    
                    def GIT_TAG_AT_COMMIT = sh(
                        script: "git describe --exact-match --tags HEAD 2>/dev/null || echo 'No tag'",
                        returnStdout: true
                    ).trim()
                    
                    // Store in environment for later use
                    env.GIT_COMMIT_SHORT = GIT_COMMIT_SHORT
                    env.GIT_COMMIT_MESSAGE = GIT_COMMIT_MESSAGE
                    
                    echo """
                    ===========================================
                    GIT INFORMATION
                    ===========================================
                    Jenkins Variables:
                      BRANCH_NAME : ${env.BRANCH_NAME ?: 'N/A'}
                      TAG_NAME    : ${env.TAG_NAME ?: 'N/A'}
                      CHANGE_ID   : ${env.CHANGE_ID ?: 'N/A'}
                    
                    Git Actual State:
                      Commit ID   : ${GIT_COMMIT_SHORT}
                      Tag at commit: ${GIT_TAG_AT_COMMIT}
                      Last message: ${GIT_COMMIT_MESSAGE}
                    ===========================================
                    """
                }
            }
        }

        /* ================= ENVIRONMENT DETECTION ================= */
        stage('Determine Deployment Strategy') {
            steps {
                script {
                    // Clear any previous values
                    env.DEPLOY_ENV = ""
                    env.TAG_TYPE = ""
                    env.IMAGE_TAG = ""
                    
                    // ===== STRATEGY 1: TAG PUSH (PRODUCTION) =====
                    if (env.TAG_NAME) {
                        echo "🎯 DETECTED: TAG PUSH - Production Deployment"
                        
                        // Validate tag format (optional)
                        if (!env.TAG_NAME.matches('^v\\d+\\.\\d+\\.\\d+$') && 
                            !env.TAG_NAME.matches('^release-.*$')) {
                            echo "⚠️  Warning: Tag doesn't follow standard pattern (vX.Y.Z or release-*)"
                        }
                        
                        env.DEPLOY_ENV = "production"
                        env.TAG_TYPE = "release"
                        env.IMAGE_TAG = env.TAG_NAME
                        
                        echo "✅ Tag confirmed: ${env.TAG_NAME}"
                        
                    // ===== STRATEGY 2: BRANCH PUSH =====
                    } else if (env.BRANCH_NAME) {
                        
                        // Validate allowed branches
                        def ALLOWED_BRANCHES = ['master', 'staging']
                        
                        if (!ALLOWED_BRANCHES.contains(env.BRANCH_NAME)) {
                            error("""
                            ❌ UNSUPPORTED BRANCH: ${env.BRANCH_NAME}
                            
                            Allowed branches are: ${ALLOWED_BRANCHES.join(', ')}
                            
                            If this is a feature branch, please:
                            1. Merge to staging first
                            2. Test in staging environment
                            3. Follow proper release workflow
                            """)
                        }
                        
                        // Branch-specific logic
                        switch(env.BRANCH_NAME) {
                            case "staging":
                                echo "🚀 DETECTED: STAGING BRANCH - Staging Deployment"
                                env.DEPLOY_ENV = "staging"
                                env.TAG_TYPE = "commit"
                                env.IMAGE_TAG = "staging-${env.GIT_COMMIT_SHORT}"
                                break
                                
                            case "master":
                                echo "⚠️  DETECTED: MASTER BRANCH DIRECT PUSH"
                                error("""
                                ❌ DIRECT MASTER PUSH NOT ALLOWED
                                
                                Production deployments are ONLY allowed via tags.
                                
                                CORRECT WORKFLOW:
                                1. git checkout master
                                2. git merge staging
                                3. git push origin master
                                4. git tag vX.Y.Z -m "Release vX.Y.Z"
                                5. git push origin vX.Y.Z
                                
                                This ensures:
                                • Proper versioning
                                • Rollback capability
                                • Audit trail
                                """)
                        }
                        
                    // ===== STRATEGY 3: UNKNOWN TRIGGER =====
                    } else {
                        error("""
                        ❌ UNKNOWN TRIGGER TYPE
                        
                        Neither branch nor tag detected.
                        This might be a webhook issue or unsupported trigger.
                        
                        Please ensure:
                        1. Pipeline is triggered by branch push or tag push
                        2. Jenkins multibranch is configured with 'Discover tags'
                        3. Webhook is sending correct events
                        """)
                    }
                    
                    // Final validation
                    if (!env.DEPLOY_ENV) {
                        error("❌ Failed to determine deployment environment")
                    }
                    
                    echo """
                    ===========================================
                    DEPLOYMENT SUMMARY
                    ===========================================
                    Trigger Type : ${env.TAG_NAME ? 'TAG' : 'BRANCH'}
                    Branch       : ${env.BRANCH_NAME ?: 'N/A'}
                    Tag          : ${env.TAG_NAME ?: 'N/A'}
                    Environment  : ${env.DEPLOY_ENV.toUpperCase()}
                    Image Tag    : ${env.IMAGE_TAG}
                    Commit       : ${env.GIT_COMMIT_SHORT}
                    ===========================================
                    """
                }
            }
        }

        /* ================= DOCKER BUILD ================= */
        stage('Build Docker Image') {
            when {
                expression { 
                    env.DEPLOY_ENV == "staging" || env.DEPLOY_ENV == "production" 
                }
            }
            steps {
                script {
                    echo "🔨 Building Docker image: ${env.IMAGE_REPO}:${env.IMAGE_TAG}"
                    
                    // Check if Dockerfile exists
                    def dockerfileExists = fileExists('Dockerfile')
                    if (!dockerfileExists) {
                        error("❌ Dockerfile not found in repository root")
                    }
                }
            }
        }

        /* ================= DOCKER PUSH ================= */
        stage('Push Docker Image') {
            when {
                expression { 
                    env.DEPLOY_ENV == "staging" || env.DEPLOY_ENV == "production" 
                }
            }
            steps {
                withCredentials([
                    usernamePassword(
                        credentialsId: env.DOCKER_CREDENTIALS_ID,
                        usernameVariable: 'DOCKER_USER',
                        passwordVariable: 'DOCKER_PASS'
                    )
                ]) {
                    script {
                        echo "📦 Pushing image to registry..."
                        
                        sh """
                            # Login to Docker Hub
                            echo \$DOCKER_PASS | docker login -u \$DOCKER_USER --password-stdin
                            
                            # Build with cache and progress
                            echo "Building image ${env.IMAGE_REPO}:${env.IMAGE_TAG}..."
                            docker build \
                                --progress=plain \
                                --no-cache \
                                -t ${env.IMAGE_REPO}:${env.IMAGE_TAG} .
                            
                            # Push the image
                            echo "Pushing image to registry..."
                            docker push ${env.IMAGE_REPO}:${env.IMAGE_TAG}
                            
                            # Additional tagging for production
                        """
                        
                        // Add latest tag for production releases
                        if (env.DEPLOY_ENV == "production") {
                            echo "🏷️  Adding 'latest' tag for production..."
                            sh """
                                docker tag ${env.IMAGE_REPO}:${env.IMAGE_TAG} ${env.IMAGE_REPO}:latest
                                docker push ${env.IMAGE_REPO}:latest
                            """
                        }
                        
                        // Logout after push
                        sh "docker logout"
                    }
                }
            }
        }

        /* ================= DEPLOYMENT ================= */
        stage('Deploy to Environment') {
            when {
                expression { 
                    env.DEPLOY_ENV == "staging" || env.DEPLOY_ENV == "production" 
                }
            }
            steps {
                script {
                    echo "🚀 Starting deployment to ${env.DEPLOY_ENV.toUpperCase()}"
                    
                    // Environment-specific deployment logic
                    if (env.DEPLOY_ENV == "staging") {
                        echo """
                        ===========================================
                        STAGING DEPLOYMENT
                        ===========================================
                        Image: ${env.IMAGE_REPO}:${env.IMAGE_TAG}
                        
                        Next steps would typically include:
                        1. Update Kubernetes deployment
                        2. Run smoke tests
                        3. Notify team
                        ===========================================
                        """
                        
                        // Example: Update K8s deployment
                        // sh "kubectl set image deployment/myapp myapp=${env.IMAGE_REPO}:${env.IMAGE_TAG} -n staging"
                        
                    } else if (env.DEPLOY_ENV == "production") {
                        echo """
                        ===========================================
                        PRODUCTION DEPLOYMENT
                        ===========================================
                        Image: ${env.IMAGE_REPO}:${env.IMAGE_TAG}
                        Latest: ${env.IMAGE_REPO}:latest
                        
                        Next steps would typically include:
                        1. Update production K8s deployment
                        2. Run health checks
                        3. Notify stakeholders
                        4. Update deployment tracker
                        ===========================================
                        """
                        
                        // Example: Blue-green or canary deployment
                        // sh "kubectl apply -f k8s/production.yaml"
                    }
                }
            }
        }

        /* ================= VERIFICATION ================= */
        stage('Verification') {
            when {
                expression { env.DEPLOY_ENV == "production" }
            }
            steps {
                script {
                    echo "✅ Production deployment verification"
                    
                    // Add verification steps
                    echo """
                    Verification checklist:
                    ✓ Docker image pushed: ${env.IMAGE_REPO}:${env.IMAGE_TAG}
                    ✓ Latest tag updated: ${env.IMAGE_REPO}:latest
                    ✓ Tag version: ${env.TAG_NAME}
                    
                    Manual verification required:
                    1. Check application health
                    2. Verify new features
                    3. Monitor error rates
                    """
                }
            }
        }
    }
}

//not triggered