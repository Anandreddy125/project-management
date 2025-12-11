pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
    }

    environment {
        GIT_REPO              = "https://github.com/Anandreddy125/project-management.git"
        GIT_CREDENTIALS_ID    = "terra-github"
        DOCKER_CREDENTIALS_ID = "anand-dockerhub"
    }

    parameters {
        choice(name: 'BUILD_TYPE', choices: ['STAGING', 'PRODUCTION'], description: 'Select build type')
        choice(name: 'BRANCH_PARAM', choices: ['main', 'master', 'staging'], description: 'Select branch to build manually')
        booleanParam(name: 'ROLLBACK', defaultValue: false, description: 'Rollback to TARGET_VERSION instead of deploy')
        string(name: 'TARGET_VERSION', defaultValue: '', description: 'Target Docker tag for rollback (if enabled)')
    }

    triggers {
        githubPush()
    }

    stages {

        stage('Clean Workspace') {
            steps { cleanWs() }
        }

        stage('Checkout Code') {
            steps {
                script {
                    def branchName = env.BRANCH_NAME ?: params.BRANCH_PARAM
                    def buildType = params.BUILD_TYPE
                    
                    echo "🔹 Build Type: ${buildType}"
                    echo "🔹 Branch: ${branchName}"
                    
                    if (buildType == 'PRODUCTION') {
                        // Production builds check out tags
                        echo "🔹 Checking out TAGS for PRODUCTION build..."
                        checkout([$class: 'GitSCM',
                            branches: [[name: "refs/tags/*"]],
                            userRemoteConfigs: [[
                                url: env.GIT_REPO,
                                credentialsId: env.GIT_CREDENTIALS_ID
                            ]],
                            extensions: [
                                [$class: 'CloneOption', shallow: false, noTags: false],
                                [$class: 'CheckoutOption']
                            ]
                        ])
                        
                        // Identify branch containing this tag
                        env.ACTUAL_BRANCH = sh(script: "git branch -r --contains HEAD | sed 's/origin\\///' | head -1", returnStdout: true).trim()
                        
                    } else {
                        // Staging builds check out specific branch
                        echo "🔹 Checking out branch: ${branchName}"
                        checkout([$class: 'GitSCM',
                            branches: [[name: "*/${branchName}"]],
                            userRemoteConfigs: [[
                                url: env.GIT_REPO,
                                credentialsId: env.GIT_CREDENTIALS_ID
                            ]]
                        ])
                        env.ACTUAL_BRANCH = branchName
                    }
                    
                    echo "✔ Git Branch/Tag: ${env.ACTUAL_BRANCH}"
                }
            }
        }

        stage('Determine Environment') {
            steps {
                script {
                    def buildType = params.BUILD_TYPE
                    
                    if (buildType == 'PRODUCTION') {
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/farhan-testing"
                        env.KUBERNETES_CREDENTIALS_ID = "k3s-report-staging1"
                        env.DEPLOYMENT_FILE = "prod-reports.yaml"
                        env.DEPLOYMENT_NAME = "prod-reports-api"
                        env.TAG_TYPE = "release"
                        
                        // Auto-detect if tag is on staging branch
                        def tagSourceBranch = env.ACTUAL_BRANCH
                        if (tagSourceBranch == "staging") {
                            echo "⚠️  WARNING: Production tag is on staging branch!"
                        }
                        
                    } else {
                        // STAGING build
                        if (env.ACTUAL_BRANCH == "main" || env.ACTUAL_BRANCH == "staging") {
                            env.DEPLOY_ENV = "staging"
                            env.IMAGE_NAME = "anrs125/sample-private"
                            env.KUBERNETES_CREDENTIALS_ID = "reports-staging1"
                            env.DEPLOYMENT_FILE = "staging-report.yaml"
                            env.DEPLOYMENT_NAME = "staging-reports-api"
                            env.TAG_TYPE = "commit"
                        } else {
                            error("Unsupported branch for staging: ${env.ACTUAL_BRANCH}")
                        }
                    }

                    echo """
                    =============================
                       DEPLOYMENT CONFIGURATION
                    =============================
                    Build Type:     ${buildType}
                    Branch/Tag:     ${env.ACTUAL_BRANCH}
                    Deploy Env:     ${env.DEPLOY_ENV}
                    Docker Repo:    ${env.IMAGE_NAME}
                    Tag Mode:       ${env.TAG_TYPE}
                    Deployment:     ${env.DEPLOYMENT_NAME}
                    Namespace:      ${env.NAMESPACE ?: 'Not set'}
                    Deployment File: ${env.DEPLOYMENT_FILE}
                    =============================
                    """
                }
            }
        }

        stage('Trivy Filesystem Scan') {
            steps {
                script {
                    echo "Running Trivy filesystem scan..."
                    sh "trivy fs . --severity HIGH,CRITICAL > trivyfs.txt || true"
                    echo "Filesystem scan completed — saved in trivyfs.txt"
                }
            }
        }

        stage('Generate Docker Tag') {
            steps {
                script {
                    def commitId = sh(script: "git rev-parse HEAD | cut -c1-7", returnStdout: true).trim()
                    def imageTag = ""

                    if (params.ROLLBACK) {
                        if (!params.TARGET_VERSION?.trim()) {
                            error("Rollback requested but no TARGET_VERSION provided.")
                        }
                        imageTag = params.TARGET_VERSION.trim()
                        echo "🔸 Rollback mode - Using provided tag: ${imageTag}"

                    } else if (env.TAG_TYPE == "commit") {
                        // STAGING builds → staging-<commitId>
                        imageTag = "staging-${commitId}"
                        echo "🔸 Staging build - Commit-based tag: ${imageTag}"

                    } else if (env.TAG_TYPE == "release") {
                        // PRODUCTION builds → extract version from Git tag
                        def gitTag = sh(
                            script: "git name-rev --name-only --tags HEAD | sed 's/\\^.*//'",
                            returnStdout: true
                        ).trim()

                        if (gitTag && gitTag != "undefined") {
                            echo "✔ Production Git Tag detected: ${gitTag}"
                            imageTag = gitTag
                        } else {
                            // Fallback: check commit message (for backward compatibility)
                            def commitMsg = sh(script: "git log -1 --pretty=%B", returnStdout: true).trim()
                            echo "No Git tag found, checking commit message: ${commitMsg}"
                            
                            def version = commitMsg =~ /(v[0-9]+\.[0-9]+\.[0-9]+)/
                            if (version) {
                                imageTag = version[0]
                                echo "⚠️  Using version from commit message (fallback): ${imageTag}"
                            } else {
                                error("❌ No Git tag found on commit! For production builds, push a tag:\n" +
                                      "  git tag v2.0.3 && git push origin v2.0.3")
                            }
                        }
                    }

                    env.IMAGE_TAG = imageTag
                    echo "==============================="
                    echo "✅ Final Docker Image Tag: ${env.IMAGE_TAG}"
                    echo "==============================="
                }
            }
        }

        stage('🔐 Docker Login') {
            steps {
                script {
                    withCredentials([usernamePassword(credentialsId: env.DOCKER_CREDENTIALS_ID,
                        usernameVariable: 'DOCKER_USER', passwordVariable: 'DOCKER_PASSWORD')]) {

                        sh """
                            echo ${DOCKER_PASSWORD} | docker login -u ${DOCKER_USER} --password-stdin
                        """
                    }
                }
            }
        }

        stage('Docker Build & Push') {
            when { expression { return !params.ROLLBACK } }
            steps {
                script {
                    def imageFull = "${env.IMAGE_NAME}:${env.IMAGE_TAG}"
                    echo "🚀 Building Docker image: ${imageFull}"
                    
                    // Different build strategies if needed
                    def buildType = params.BUILD_TYPE
                    def buildArgs = ""
                    
                    if (buildType == 'PRODUCTION') {
                        buildArgs = "--build-arg ENVIRONMENT=production --build-arg BUILD_TYPE=prod"
                    } else {
                        buildArgs = "--build-arg ENVIRONMENT=staging --build-arg BUILD_TYPE=staging"
                    }

                    sh """
                        docker build --pull --no-cache ${buildArgs} -t ${imageFull} .
                        docker push ${imageFull}
                        
                        # Also tag as latest for staging (optional)
                        ${buildType == 'STAGING' ? 'docker tag ${imageFull} ${env.IMAGE_NAME}:latest && docker push ${env.IMAGE_NAME}:latest' : ''}
                    """
                }
            }
        }
    }
}