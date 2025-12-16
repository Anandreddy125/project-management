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
        choice(name: 'BRANCH_PARAM', choices: ['staging', 'master'], description: 'Select branch to build manually')
        booleanParam(name: 'ROLLBACK', defaultValue: false, description: 'Rollback to TARGET_VERSION instead of deploy')
        string(name: 'TARGET_VERSION', defaultValue: '', description: 'Target Docker tag for rollback (if enabled)')
    }
    triggers {
        // Option 1: SCM polling (triggers on both branches and tags)
        pollSCM('H/2 * * * *')  // Poll every 2 minutes
        
        // Option 2: GitHub webhook (configure in GitHub webhook settings)
        // githubPush()
        
        // Option 3: GitHub pull request builder (if using PRs)
        // githubPullRequest(
        //     adminlist: '',
        //     allowMembersOfWhitelistedOrgsAsAdmin: true,
        //     orgslist: '',
        //     cron: '',
        //     triggerPhrase: '',
        //     onlyTriggerPhrase: false,
        //     useGitHubHooks: true,
        //     permitAll: false,
        //     autoCloseFailedPullRequests: false,
        //     displayBuildErrorsOnDownstreamBuilds: false,
        //     whiteListTargetBranches: [[name: 'master'], [name: 'staging']]
        // )
    }
    stages {
        stage('Clean Workspace') {
            steps { cleanWs() }
        }
        stage('Checkout Code') {
            steps {
                script {
                    def branchName = env.BRANCH_NAME ?: params.BRANCH_PARAM
                    echo ":small_blue_diamond: Checking out branch: ${branchName}"
                    
                    // Get the actual branch/tag that triggered the build
                    def changeBranch = env.GIT_BRANCH ?: branchName
                    echo "Triggered by: ${changeBranch}"
                    
                    checkout([$class: 'GitSCM',
                        branches: [[name: "*/${branchName}"]],
                        userRemoteConfigs: [[
                            url: env.GIT_REPO,
                            credentialsId: env.GIT_CREDENTIALS_ID,
                            refspec: "+refs/heads/*:refs/remotes/origin/* +refs/tags/*:refs/tags/*"
                        ]],
                        extensions: [[$class: 'LocalBranch', localBranch: "**"]],
                        doGenerateSubmoduleConfigurations: false,
                        submoduleCfg: []
                    ])
                    
                    // Try to detect if this is a tag build
                    def isTagBuild = false
                    def tagName = ""
                    
                    try {
                        // Check if HEAD points to a tag
                        tagName = sh(
                            script: "git describe --tags --exact-match HEAD 2>/dev/null || true",
                            returnStdout: true
                        ).trim()
                        
                        if (tagName) {
                            isTagBuild = true
                            echo "🎯 This is a TAG build: ${tagName}"
                            env.IS_TAG_BUILD = "true"
                            env.TAG_NAME = tagName
                        }
                    } catch (Exception e) {
                        echo "Not a tag build: ${e.message}"
                    }
                    
                    env.ACTUAL_BRANCH = branchName
                    env.IS_TAG_BUILD = isTagBuild.toString()
                }
            }
        }
        stage('Determine Environment') {
            steps {
                script {
                    def isTagBuild = env.IS_TAG_BUILD.toBoolean()
                    
                    if (isTagBuild) {
                        // Tag builds always go to production
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.TAG_TYPE = "release"
                        echo "🚀 TAG BUILD detected: ${env.TAG_NAME} → Production"
                    } else if (env.ACTUAL_BRANCH == "staging") {
                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.TAG_TYPE = "commit"
                    } else if (env.ACTUAL_BRANCH == "master") {
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.TAG_TYPE = "release"
                    } else {
                        error("Unsupported branch: ${env.ACTUAL_BRANCH}")
                    }
                    
                    echo """
                    Environment Info
                    ----------------------
                    Branch: ${env.ACTUAL_BRANCH}
                    Tag Build: ${isTagBuild}
                    Tag: ${env.TAG_NAME ?: 'N/A'}
                    Deploy: ${env.DEPLOY_ENV}
                    Repo:   ${env.IMAGE_NAME}
                    Mode:   ${env.TAG_TYPE}
                    """
                }
            }
        }
        stage('Generate Docker Tag') {
            steps {
                script {
                    def isTagBuild = env.IS_TAG_BUILD.toBoolean()
                    def commitId = sh(script: "git rev-parse HEAD | cut -c1-7", returnStdout: true).trim()
                    def imageTag = ""
                    
                    if (params.ROLLBACK) {
                        if (!params.TARGET_VERSION?.trim()) {
                            error("Rollback requested but no TARGET_VERSION provided.")
                        }
                        imageTag = params.TARGET_VERSION.trim()
                    } else if (isTagBuild) {
                        // Use the actual tag name
                        imageTag = env.TAG_NAME
                        echo "🎯 Using Git tag for Docker tag: ${imageTag}"
                    } else if (env.TAG_TYPE == "commit") {
                        imageTag = "staging-${commitId}"
                    } else if (env.TAG_TYPE == "release") {
                        // For master branch (non-tag builds), use commit hash
                        imageTag = "prod-${commitId}"
                    }
                    
                    env.IMAGE_TAG = imageTag
                    echo ":rocket: FINAL Docker Tag: ${env.IMAGE_TAG}"
                    
                    // Save tag info for downstream jobs
                    currentBuild.description = "${env.DEPLOY_ENV.toUpperCase()} - ${env.IMAGE_TAG}"
                }
            }
        }
        stage('Docker Login') {
            steps {
                script {
                    withCredentials([usernamePassword(credentialsId: env.DOCKER_CREDENTIALS_ID,
                        usernameVariable: 'DOCKER_USER', passwordVariable: 'DOCKER_PASSWORD')]) {
                        sh "echo ${DOCKER_PASSWORD} | docker login -u ${DOCKER_USER} --password-stdin"
                    }
                }
            }
        }
        stage('Docker Build & Push') {
            when { 
                expression { 
                    return !params.ROLLBACK 
                }
            }
            steps {
                script {
                    def imageFull = "${env.IMAGE_NAME}:${env.IMAGE_TAG}"
                    def latestTag = ""
                    
                    // Also tag as latest for staging, or specific pattern for production
                    if (env.DEPLOY_ENV == "staging") {
                        latestTag = "${env.IMAGE_NAME}:staging-latest"
                    } else if (env.DEPLOY_ENV == "production" && env.IS_TAG_BUILD.toBoolean()) {
                        latestTag = "${env.IMAGE_NAME}:latest"
                    }
                    
                    echo "Building Docker image: ${imageFull}"
                    sh """
                        docker build --pull --no-cache -t ${imageFull} .
                        
                        # Push the main tag
                        docker push ${imageFull}
                        
                        # Push latest tag if applicable
                        ${latestTag ? "docker tag ${imageFull} ${latestTag} && docker push ${latestTag}" : "echo 'No latest tag for this build'"}
                    """
                    sh "docker logout"
                    
                    // Archive the tag for future reference
                    writeFile file: 'build-info.txt', text: """
                    Build Information
                    -----------------
                    Environment: ${env.DEPLOY_ENV}
                    Docker Tag: ${env.IMAGE_TAG}
                    Git Branch: ${env.ACTUAL_BRANCH}
                    Git Tag: ${env.TAG_NAME ?: 'None'}
                    Commit: ${sh(script: "git rev-parse HEAD | cut -c1-7", returnStdout: true).trim()}
                    Build Time: ${new Date()}
                    """
                    archiveArtifacts artifacts: 'build-info.txt'
                }
            }
        }
        
        // Optional: Add a notification stage
        stage('Notify') {
            steps {
                script {
                    echo "✅ Build completed successfully!"
                    echo "📦 Image: ${env.IMAGE_NAME}:${env.IMAGE_TAG}"
                    echo "🌍 Environment: ${env.DEPLOY_ENV}"
                    
                    // Add notification logic here (Slack, Email, etc.)
                    // slackSend color: 'good', message: "Build successful: ${env.JOB_NAME} #${env.BUILD_NUMBER}"
                }
            }
        }
    }
    
    post {
        success {
            echo "🎉 Pipeline completed successfully!"
        }
        failure {
            echo "❌ Pipeline failed!"
            // slackSend color: 'danger', message: "Build failed: ${env.JOB_NAME} #${env.BUILD_NUMBER}"
        }
        always {
            echo "🧹 Cleaning up..."
            // Clean up Docker images
            sh 'docker system prune -f || true'
        }
    }
}