pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
    }

    environment {
        SCANNER_HOME          = tool('sonar-scanner')
        GIT_REPO              = "https://github.com/Anandreddy125/project-management.git"
        GIT_CREDENTIALS_ID    = "terra-github"
        DOCKER_CREDENTIALS_ID = "anand-dockerhub"
    }

    parameters {
        choice(name: 'BUILD_TYPE', choices: ['AUTO', 'STAGING', 'PRODUCTION'], description: 'Select build type (AUTO = detect from branch)')
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
                    
                    echo "🔹 Initial Build Type Selection: ${buildType}"
                    echo "🔹 Branch Parameter: ${branchName}"
                    echo "🔹 Branch Name from Jenkins: ${env.BRANCH_NAME ?: 'Not set'}"
                    
                    if (buildType == 'AUTO') {
                        // Auto-detect based on branch
                        if (branchName == 'master') {
                            env.BUILD_TYPE = 'PRODUCTION'
                        } else {
                            env.BUILD_TYPE = 'STAGING'
                        }
                    } else {
                        env.BUILD_TYPE = buildType
                    }
                    
                    echo "🔹 Final Build Type: ${env.BUILD_TYPE}"
                    
                    if (env.BUILD_TYPE == 'PRODUCTION') {
                        // Production builds - check out master branch
                        echo "🔹 Checking out MASTER branch for PRODUCTION build..."
                        checkout([$class: 'GitSCM',
                            branches: [[name: "*/master"]],
                            userRemoteConfigs: [[
                                url: env.GIT_REPO,
                                credentialsId: env.GIT_CREDENTIALS_ID
                            ]]
                        ])
                        env.ACTUAL_BRANCH = "master"
                        
                    } else {
                        // STAGING builds check out specific branch
                        echo "🔹 Checking out branch: ${branchName} for STAGING build"
                        checkout([$class: 'GitSCM',
                            branches: [[name: "*/${branchName}"]],
                            userRemoteConfigs: [[
                                url: env.GIT_REPO,
                                credentialsId: env.GIT_CREDENTIALS_ID
                            ]]
                        ])
                        env.ACTUAL_BRANCH = branchName
                    }
                    
                    echo "✔ Git Branch: ${env.ACTUAL_BRANCH}"
                }
            }
        }

        stage('Determine Environment') {
            steps {
                script {
                    if (env.BUILD_TYPE == 'PRODUCTION') {
                        // PRODUCTION CONFIGURATION
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/farhan-testing"
                        env.KUBERNETES_CREDENTIALS_ID = "k3s-report-staging1"
                        env.DEPLOYMENT_FILE = "prod-reports.yaml"
                        env.DEPLOYMENT_NAME = "prod-reports-api"
                        env.TAG_TYPE = "release"
                        
                    } else {
                        // STAGING CONFIGURATION
                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/sample-private"
                        env.KUBERNETES_CREDENTIALS_ID = "reports-staging1"
                        env.DEPLOYMENT_FILE = "staging-report.yaml"
                        env.DEPLOYMENT_NAME = "staging-reports-api"
                        env.TAG_TYPE = "commit"
                        
                        // Validate staging branches
                        def validStagingBranches = ['main', 'staging', 'develop', 'feature/*', 'hotfix/*']
                        def isValid = validStagingBranches.any { pattern ->
                            if (pattern.endsWith('/*')) {
                                return env.ACTUAL_BRANCH.startsWith(pattern.replace('/*', ''))
                            }
                            return env.ACTUAL_BRANCH == pattern
                        }
                        
                        if (!isValid) {
                            echo "⚠️  WARNING: Branch '${env.ACTUAL_BRANCH}' is not a typical staging branch, but proceeding anyway..."
                        }
                    }

                    echo """
                    =============================
                       DEPLOYMENT CONFIGURATION
                    =============================
                    Build Type:     ${env.BUILD_TYPE}
                    Branch:         ${env.ACTUAL_BRANCH}
                    Deploy Env:     ${env.DEPLOY_ENV}
                    Docker Repo:    ${env.IMAGE_NAME}
                    Tag Mode:       ${env.TAG_TYPE}
                    Deployment:     ${env.DEPLOYMENT_NAME}
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
                        // PRODUCTION builds - check for tag in commit message
                        def commitMsg = sh(script: "git log -1 --pretty=%B", returnStdout: true).trim()
                        echo "Commit message: ${commitMsg}"
                        
                        // First check if there's a Git tag on this commit
                        def gitTag = sh(
                            script: "git describe --tags --exact-match 2>/dev/null || echo 'no-tag'",
                            returnStdout: true
                        ).trim()
                        
                        if (gitTag && gitTag != "no-tag") {
                            echo "✔ Git Tag detected: ${gitTag}"
                            imageTag = gitTag
                        } else {
                            // Fallback: extract version from commit message
                            def version = commitMsg =~ /(v[0-9]+\.[0-9]+\.[0-9]+)/
                            if (version) {
                                imageTag = version[0]
                                echo "⚠️  Using version from commit message: ${imageTag}"
                            } else {
                                // Last resort: use timestamp
                                def timestamp = sh(script: "date +'%Y%m%d-%H%M%S'", returnStdout: true).trim()
                                imageTag = "prod-${timestamp}-${commitId}"
                                echo "⚠️  No version found, using timestamp: ${imageTag}"
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
                    
                    // Add build arguments based on environment
                    def buildArgs = "--build-arg ENVIRONMENT=${env.DEPLOY_ENV}"
                    
                    if (env.BUILD_TYPE == 'PRODUCTION') {
                        buildArgs += " --build-arg NODE_ENV=production --build-arg APP_ENV=prod"
                    } else {
                        buildArgs += " --build-arg NODE_ENV=development --build-arg APP_ENV=staging"
                    }

                    sh """
                        docker build --pull --no-cache ${buildArgs} -t ${imageFull} .
                        docker push ${imageFull}
                        
                        # Tag as latest for staging (optional)
                        ${env.BUILD_TYPE == 'STAGING' ? 'docker tag ${imageFull} ${env.IMAGE_NAME}:latest-staging && docker push ${env.IMAGE_NAME}:latest-staging' : ''}
                        
                        # Also tag with commit SHA for traceability
                        docker tag ${imageFull} ${env.IMAGE_NAME}:git-${sh(script: 'git rev-parse --short HEAD', returnStdout: true).trim()}
                        docker push ${env.IMAGE_NAME}:git-${sh(script: 'git rev-parse --short HEAD', returnStdout: true).trim()}
                    """
                    
                    echo "✅ Image pushed successfully!"
                    echo "📦 Primary Tag: ${imageFull}"
                }
            }
        }
    }
}